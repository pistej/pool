package generator_test

import (
	"testing"

	"github.com/sfrpc/pool/tools/protoc-gen-sfrpc/generator"
	"google.golang.org/protobuf/compiler/protogen"
)

func TestGetPhpNamespace(t *testing.T) {
	tests := []struct {
		name          string
		goPackageName string
		expected      string
	}{
		{
			name:          "simple package",
			goPackageName: "Demo",
			expected:      "Generated\\Demo",
		},
		{
			name:          "nested package",
			goPackageName: "App_Grpc_Demo",
			expected:      "Generated\\App\\Grpc\\Demo",
		},
		{
			name:          "empty string",
			goPackageName: "",
			expected:      "Generated\\",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			file := &protogen.File{
				GoPackageName: protogen.GoPackageName(tt.goPackageName),
			}
			result := generator.GetPhpNamespace(file)
			if result != tt.expected {
				t.Errorf("expected %q, got %q", tt.expected, result)
			}
		})
	}
}

func TestGenerateFile_EmptyServices(t *testing.T) {
	file := &protogen.File{}

	generator.GenerateFile(nil, file)
}
