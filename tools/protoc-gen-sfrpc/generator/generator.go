package generator

import (
	"strings"

	"google.golang.org/protobuf/compiler/protogen"
	"google.golang.org/protobuf/reflect/protoreflect"
	"google.golang.org/protobuf/types/descriptorpb"
)

// GenerateFile generates the PHP interface, client, and proxy classes for given proto file.
func GenerateFile(gen *protogen.Plugin, file *protogen.File) {
	if len(file.Services) == 0 {
		return
	}

	for _, service := range file.Services {
		generateInterface(gen, file, service)
		generateClient(gen, file, service)
		generateProxy(gen, file, service)
	}
}

func GetPhpNamespace(file protoreflect.FileDescriptor) string {
	if file != nil && file.Options() != nil {
		if options := file.Options().(*descriptorpb.FileOptions); options != nil {
			if options.PhpNamespace != nil {
				return *options.PhpNamespace
			}
		}
	}

	// Simple mapping based on protoc-gen-php default logic.
	return "Generated\\" + strings.ReplaceAll(string(file.Package()), ".", "\\")
}

func GetPhpClassName(message protoreflect.MessageDescriptor) string {
	namespace := GetPhpNamespace(message.ParentFile())
	return "\\" + namespace + "\\" + string(message.Name())
}
