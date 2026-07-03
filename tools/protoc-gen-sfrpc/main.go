package main

import (
	"log"
	"strconv"

	"github.com/sfrpc/pool/tools/protoc-gen-sfrpc/generator"
	"google.golang.org/protobuf/compiler/protogen"
	"google.golang.org/protobuf/types/pluginpb"
)

// parseDebugParam interprets the `debug` plugin option, e.g.
// `protoc --sfrpc_opt=debug ...` or `opt: debug` in buf.gen.yaml (bare, with
// no value, meaning "enabled"), or an explicit `debug=true`/`debug=false`.
// Parameters other than `debug` are ignored and leave debug unchanged.
func parseDebugParam(name, value string, debug bool) (bool, error) {
	if name != "debug" {
		return debug, nil
	}
	if value == "" {
		return true, nil
	}
	return strconv.ParseBool(value)
}

func main() {
	var debug bool

	protogen.Options{
		ParamFunc: func(name, value string) error {
			parsed, err := parseDebugParam(name, value, debug)
			if err != nil {
				return err
			}
			debug = parsed
			return nil
		},
	}.Run(func(gen *protogen.Plugin) error {
		gen.SupportedFeatures = uint64(pluginpb.CodeGeneratorResponse_FEATURE_PROTO3_OPTIONAL)

		for _, f := range gen.Files {
			if !f.Generate {
				continue
			}
			generator.GenerateFile(gen, f)
		}
		return nil
	})

	if debug {
		log.Println("Custom sfrpc plugin finished processing")
	}
}
