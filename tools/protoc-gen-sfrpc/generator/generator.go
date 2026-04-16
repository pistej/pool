package generator

import (
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
