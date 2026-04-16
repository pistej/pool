package generator

import (
	"strings"

	"google.golang.org/protobuf/compiler/protogen"
)

func newPhpFile(gen *protogen.Plugin, file *protogen.File, service *protogen.Service, suffix string) *protogen.GeneratedFile {
	namespace := GetPhpNamespace(file.Desc)
	dir := strings.ReplaceAll(namespace, "\\", "/")
	filename := dir + "/" + service.GoName + suffix
	return gen.NewGeneratedFile(filename, file.GoImportPath)
}

func generateInterface(gen *protogen.Plugin, file *protogen.File, service *protogen.Service) {
	namespace := GetPhpNamespace(file.Desc)
	g := newPhpFile(gen, file, service, "ClientInterface.php")

	g.P("<?php")
	g.P()
	g.P("declare(strict_types=1);")
	g.P()
	g.P("namespace ", namespace, ";")
	g.P()
	g.P("use Sfrpc\\Pool\\Grpc\\ClientContext;")
	g.P()
	g.P("interface ", service.GoName, "ClientInterface")
	g.P("{")
	for _, method := range service.Methods {
		reqType := GetPhpClassName(method.Input.Desc)
		respType := GetPhpClassName(method.Output.Desc)
		g.P("    public function ", method.GoName, "(", reqType, " $request, ?ClientContext $context = null): ", respType, ";")
	}
	g.P("}")
}

func generateClient(gen *protogen.Plugin, file *protogen.File, service *protogen.Service) {
	namespace := GetPhpNamespace(file.Desc)
	g := newPhpFile(gen, file, service, "Client.php")

	g.P("<?php")
	g.P()
	g.P("declare(strict_types=1);")
	g.P()
	g.P("namespace ", namespace, ";")
	g.P()
	g.P("use Sfrpc\\Pool\\Grpc\\BaseClient;")
	g.P("use Sfrpc\\Pool\\Grpc\\ClientContext;")
	g.P()
	g.P("class ", service.GoName, "Client extends BaseClient implements ", service.GoName, "ClientInterface")
	g.P("{")
	for _, method := range service.Methods {
		reqType := GetPhpClassName(method.Input.Desc)
		respType := GetPhpClassName(method.Output.Desc)
		path := "/" + string(service.Desc.FullName()) + "/" + string(method.Desc.Name())

		g.P("    public function ", method.GoName, "(", reqType, " $request, ?ClientContext $context = null): ", respType)
		g.P("    {")
		g.P("        return $this->simpleRequest('", path, "', $request, ", respType, "::class, $context);")
		g.P("    }")
	}
	g.P("}")
}

func generateProxy(gen *protogen.Plugin, file *protogen.File, service *protogen.Service) {
	namespace := GetPhpNamespace(file.Desc)
	g := newPhpFile(gen, file, service, "ClientProxy.php")

	g.P("<?php")
	g.P()
	g.P("declare(strict_types=1);")
	g.P()
	g.P("namespace ", namespace, ";")
	g.P()
	g.P("use Sfrpc\\Pool\\Proxy\\AbstractGrpcProxy;")
	g.P("use Sfrpc\\Pool\\Grpc\\ClientContext;")
	g.P("use Sfrpc\\Pool\\Grpc\\BaseClient;")
	g.P()
	g.P("class ", service.GoName, "ClientProxy extends AbstractGrpcProxy implements ", service.GoName, "ClientInterface")
	g.P("{")
	for _, method := range service.Methods {
		reqType := GetPhpClassName(method.Input.Desc)
		respType := GetPhpClassName(method.Output.Desc)
		path := "/" + string(service.Desc.FullName()) + "/" + string(method.Desc.Name())

		g.P("    public function ", method.GoName, "(", reqType, " $request, ?ClientContext $context = null): ", respType)
		g.P("    {")
		g.P("        return $this->executeInContext(function () use ($request, $context) {")
		g.P("            /** @var BaseClient $client */")
		g.P("            $client = $this->pool->borrow();")
		g.P("            try {")
		g.P("                return $client->simpleRequest('", path, "', $request, ", respType, "::class, $context);")
		g.P("            } finally {")
		g.P("                $this->pool->return($client);")
		g.P("            }")
		g.P("        });")
		g.P("    }")
	}
	g.P("}")
}
