package generator_test

import (
	"testing"

	"github.com/sfrpc/pool/tools/protoc-gen-sfrpc/generator"
	"google.golang.org/protobuf/reflect/protoreflect"
	"google.golang.org/protobuf/types/descriptorpb"
)

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
