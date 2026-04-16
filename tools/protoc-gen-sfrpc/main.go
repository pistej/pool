package main

import (
	"log"

	"github.com/sfrpc/pool/tools/protoc-gen-sfrpc/generator"
	"google.golang.org/protobuf/compiler/protogen"
)

func main() {
	protogen.Options{
		ParamFunc: func(name, value string) error {
			// Handle any custom plugin parameters here if needed
			return nil
		},
	}.Run(func(gen *protogen.Plugin) error {
		gen.SupportedFeatures = uint64(1) // grpc.SupportProto3Optional is usually 1 if needed

		for _, f := range gen.Files {
			if !f.Generate {
				continue
			}
			generator.GenerateFile(gen, f)
		}
		return nil
	})
	log.Println("Custom sfrpc plugin finished processing")
}
