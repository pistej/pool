package generator

import (
	"strings"

	"google.golang.org/protobuf/reflect/protoreflect"
	"google.golang.org/protobuf/types/descriptorpb"
)

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
