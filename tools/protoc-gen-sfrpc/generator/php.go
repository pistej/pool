package generator

import (
	"strings"

	"google.golang.org/protobuf/compiler/protogen"
)

func generateInterface(gen *protogen.Plugin, file *protogen.File, service *protogen.Service) {
	filename := service.GoName + "ClientInterface.php"
	g := gen.NewGeneratedFile(filename, file.GoImportPath)

	namespace := GetPhpNamespace(file)

	g.P("<?php")
	g.P("declare(strict_types=1);")
	g.P()
	g.P("namespace ", namespace, ";")
	g.P()
	g.P("use Sfrpc\\Pool\\Grpc\\ClientContext;")
	g.P()
	g.P("interface ", service.GoName, "ClientInterface")
	g.P("{")
	for _, method := range service.Methods {
		reqType := "\\" + strings.ReplaceAll(string(method.Input.Desc.FullName()), ".", "\\")
		respType := "\\" + strings.ReplaceAll(string(method.Output.Desc.FullName()), ".", "\\")
		g.P("    public function ", method.GoName, "(", reqType, " $request, ?ClientContext $context = null): ", respType, ";")
	}
	g.P("}")
}

func generateClient(gen *protogen.Plugin, file *protogen.File, service *protogen.Service) {
	filename := service.GoName + "Client.php"
	g := gen.NewGeneratedFile(filename, file.GoImportPath)

	namespace := GetPhpNamespace(file)

	g.P("<?php")
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
		reqType := "\\" + strings.ReplaceAll(string(method.Input.Desc.FullName()), ".", "\\")
		respType := "\\" + strings.ReplaceAll(string(method.Output.Desc.FullName()), ".", "\\")
		path := "/" + string(service.Desc.FullName()) + "/" + string(method.Desc.Name())

		g.P("    public function ", method.GoName, "(", reqType, " $request, ?ClientContext $context = null): ", respType)
		g.P("    {")
		g.P("        return $this->_simpleRequest('", path, "', $request, ", respType, "::class, $context);")
		g.P("    }")
	}
	g.P("}")
}

func generateProxy(gen *protogen.Plugin, file *protogen.File, service *protogen.Service) {
	filename := service.GoName + "ClientProxy.php"
	g := gen.NewGeneratedFile(filename, file.GoImportPath)

	namespace := GetPhpNamespace(file)

	g.P("<?php")
	g.P("declare(strict_types=1);")
	g.P()
	g.P("namespace ", namespace, ";")
	g.P()
	g.P("use Sfrpc\\Pool\\Proxy\\AbstractGrpcProxy;")
	g.P("use Sfrpc\\Pool\\Grpc\\ClientContext;")
	g.P()
	g.P("class ", service.GoName, "ClientProxy extends AbstractGrpcProxy implements ", service.GoName, "ClientInterface")
	g.P("{")
	for _, method := range service.Methods {
		reqType := "\\" + strings.ReplaceAll(string(method.Input.Desc.FullName()), ".", "\\")
		respType := "\\" + strings.ReplaceAll(string(method.Output.Desc.FullName()), ".", "\\")

		g.P("    public function ", method.GoName, "(", reqType, " $request, ?ClientContext $context = null): ", respType)
		g.P("    {")
		g.P("        /** @var ", service.GoName, "ClientInterface $client */")
		g.P("        $client = $this->pool->borrow();")
		g.P("        try {")
		g.P("            return $client->", method.GoName, "($request, $context);")
		g.P("        } finally {")
		g.P("            $this->pool->return($client);")
		g.P("        }")
		g.P("    }")
	}
	g.P("}")
}
