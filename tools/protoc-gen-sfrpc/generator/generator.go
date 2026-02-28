package generator

import (
	"strings"

	"google.golang.org/protobuf/compiler/protogen"
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

func GetPhpNamespace(file *protogen.File) string {
	// Simple mapping based on protoc-gen-php default logic.
	// You can enhance this to read generic php_namespace option from proto extension.
	return "Generated\\" + strings.ReplaceAll(string(file.GoPackageName), "_", "\\")
}
