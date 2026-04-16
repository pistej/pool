package generator_test

import (
	"flag"
	"os"
	"path/filepath"
	"testing"

	"github.com/sfrpc/pool/tools/protoc-gen-sfrpc/generator"
	"google.golang.org/protobuf/compiler/protogen"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/reflect/protoreflect"
	"google.golang.org/protobuf/types/descriptorpb"
	"google.golang.org/protobuf/types/pluginpb"
)

var update = flag.Bool("update", false, "regenerate golden files")

func buildTestRequest() *pluginpb.CodeGeneratorRequest {
	phpNs := "Wss\\Tool\\Grpc"
	strType := descriptorpb.FieldDescriptorProto_TYPE_STRING.Enum()
	optLabel := descriptorpb.FieldDescriptorProto_LABEL_OPTIONAL.Enum()

	return &pluginpb.CodeGeneratorRequest{
		FileToGenerate: []string{"test.proto"},
		ProtoFile: []*descriptorpb.FileDescriptorProto{
			{
				Name:    proto.String("test.proto"),
				Package: proto.String("fastApi"),
				Syntax:  proto.String("proto3"),
				Options: &descriptorpb.FileOptions{PhpNamespace: &phpNs, GoPackage: proto.String("./;sfrpc")},
				MessageType: []*descriptorpb.DescriptorProto{
					{Name: proto.String("GetInfoRequest"), Field: []*descriptorpb.FieldDescriptorProto{
						{Name: proto.String("domain"), Number: proto.Int32(1), Type: strType, Label: optLabel},
					}},
					{Name: proto.String("DomainInfo"), Field: []*descriptorpb.FieldDescriptorProto{
						{Name: proto.String("name"), Number: proto.Int32(1), Type: strType, Label: optLabel},
					}},
				},
				Service: []*descriptorpb.ServiceDescriptorProto{
					{Name: proto.String("Domain"), Method: []*descriptorpb.MethodDescriptorProto{
						{Name: proto.String("GetInfo"), InputType: proto.String(".fastApi.GetInfoRequest"), OutputType: proto.String(".fastApi.DomainInfo")},
					}},
				},
			},
		},
	}
}

func TestGenerateFile(t *testing.T) {
	plugin, err := protogen.Options{}.New(buildTestRequest())
	if err != nil {
		t.Fatalf("protogen.Options{}.New: %v", err)
	}

	for _, f := range plugin.Files {
		if f.Generate {
			generator.GenerateFile(plugin, f)
		}
	}

	resp := plugin.Response()
	if resp.Error != nil {
		t.Fatalf("plugin error: %s", *resp.Error)
	}

	goldenDir := filepath.Join("..", "testdata", "golden")

	for _, file := range resp.File {
		name := filepath.Base(*file.Name)
		golden := filepath.Join(goldenDir, name)
		content := *file.Content

		if *update {
			if err := os.MkdirAll(goldenDir, 0755); err != nil {
				t.Fatal(err)
			}
			if err := os.WriteFile(golden, []byte(content), 0644); err != nil {
				t.Fatalf("writing %s: %v", golden, err)
			}
			t.Logf("updated %s", golden)
			continue
		}

		want, err := os.ReadFile(golden)
		if err != nil {
			t.Fatalf("golden file %q missing; run: go test -update\n%v", golden, err)
		}
		if string(want) != content {
			t.Errorf("%s: output does not match golden file\nwant:\n%s\ngot:\n%s", name, want, content)
		}
	}
}

func TestGenerateFileSkipsNoServices(t *testing.T) {
	phpNs := "Wss\\Tool\\Grpc"
	req := &pluginpb.CodeGeneratorRequest{
		FileToGenerate: []string{"empty.proto"},
		ProtoFile: []*descriptorpb.FileDescriptorProto{
			{
				Name:    proto.String("empty.proto"),
				Package: proto.String("fastApi"),
				Syntax:  proto.String("proto3"),
				Options: &descriptorpb.FileOptions{PhpNamespace: &phpNs, GoPackage: proto.String("./;sfrpc")},
			},
		},
	}

	plugin, err := protogen.Options{}.New(req)
	if err != nil {
		t.Fatalf("protogen.Options{}.New: %v", err)
	}

	for _, f := range plugin.Files {
		if f.Generate {
			generator.GenerateFile(plugin, f)
		}
	}

	if got := len(plugin.Response().File); got != 0 {
		t.Errorf("expected 0 generated files for proto with no services, got %d", got)
	}
}

func TestGetPhpNamespace(t *testing.T) {
	tests := []struct {
		name         string
		packageName  string
		phpNamespace string
		expected     string
	}{
		{
			name:        "simple package",
			packageName: "Demo",
			expected:    "Generated\\Demo",
		},
		{
			name:        "nested package",
			packageName: "App.Grpc.Demo",
			expected:    "Generated\\App\\Grpc\\Demo",
		},
		{
			name:         "php_namespace option",
			packageName:  "Demo",
			phpNamespace: "Wss\\Tool\\Grpc",
			expected:     "Wss\\Tool\\Grpc",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var options *descriptorpb.FileOptions
			if tt.phpNamespace != "" {
				options = &descriptorpb.FileOptions{
					PhpNamespace: &tt.phpNamespace,
				}
			}

			desc := &mockFileDescriptor{
				packageName: tt.packageName,
				options:     options,
			}

			result := generator.GetPhpNamespace(desc)
			if result != tt.expected {
				t.Errorf("expected %q, got %q", tt.expected, result)
			}
		})
	}
}

type mockFileDescriptor struct {
	protoreflect.FileDescriptor
	packageName string
	options     *descriptorpb.FileOptions
}

func (m *mockFileDescriptor) Package() protoreflect.FullName {
	return protoreflect.FullName(m.packageName)
}

func (m *mockFileDescriptor) Options() protoreflect.ProtoMessage {
	return m.options
}

func TestGetPhpClassName(t *testing.T) {
	phpNamespace := "Wss\\Tool\\Grpc"
	options := &descriptorpb.FileOptions{
		PhpNamespace: &phpNamespace,
	}
	fileDesc := &mockFileDescriptor{
		options: options,
	}
	messageDesc := &mockMessageDescriptor{
		name:       "GetInfoRequest",
		parentFile: fileDesc,
	}

	expected := "\\Wss\\Tool\\Grpc\\GetInfoRequest"
	result := generator.GetPhpClassName(messageDesc)
	if result != expected {
		t.Errorf("expected %q, got %q", expected, result)
	}
}

type mockMessageDescriptor struct {
	protoreflect.MessageDescriptor
	name       string
	parentFile protoreflect.FileDescriptor
}

func (m *mockMessageDescriptor) Name() protoreflect.Name {
	return protoreflect.Name(m.name)
}

func (m *mockMessageDescriptor) ParentFile() protoreflect.FileDescriptor {
	return m.parentFile
}
