# protoc-gen-sfrpc

A custom gRPC code generator for PHP, specifically designed to work with the `sfrpc/pool` Symfony bundle. It generates PHP Interfaces, concrete Clients, and Proxy wrappers that leverage the connection pool.

## Features

- **Standard Interfaces**: Emits PSR-compliant client interfaces.
- **Concrete Clients**: Native HTTP/2 gRPC clients using the `sfrpc/pool` `BaseClient`.
- **Pool Proxies**: Generates Proxy classes that automatically `borrow()` and `return()` connections from the `ConnectionPool` for every request, ensuring coroutine safety and efficient resource usage.

## Architecture

- `main.go`: The plugin entry point.
- `generator/generator.go`: Orchestrates per-file code generation.
- `generator/helpers.go`: PHP namespace and class name utilities.
- `generator/php.go`: Emits interface, client, and proxy PHP files.
- `generator/generator_test.go`: Unit tests for the generator package.
- `testdata/test.proto`: Sample proto used for manual generation testing.
- `testdata/golden/`: Committed golden PHP files used by `TestGenerateFile` to verify output correctness.
- `testdata/out/`: Output directory for `buf generate` (generated, not committed).
- `buf.gen.yaml`: Configuration for the `buf` CLI.

## Development & Usage

All development tasks are performed within the `sfrpc-go-test` Docker container.

### Building the environment

```bash
docker compose build sfrpc-go-test
docker compose up -d sfrpc-go-test
```

### Generating PHP Code

To regenerate PHP classes from `testdata/test.proto`, run:

```bash
docker compose exec sfrpc-go-test buf generate
```

Output is written to `testdata/out/`.

> [!TIP]
> The `buf.gen.yaml` is configured to use `go run main.go`. This means any changes you make to the `.go` generator source files are applied **instantly** when you run `buf generate`, without needing to recompile the binary or rebuild the Docker image.

### Running Tests

To run the Go plugin's internal tests:

```bash
docker compose exec sfrpc-go-test go test -v ./...
```

`TestGenerateFile` compares generator output against committed golden files in `testdata/golden/`. If you change the PHP generation logic, regenerate the golden files with:

```bash
docker compose exec sfrpc-go-test go test -v ./generator/ -update
```

### Manual Compilation (Optional)

Produces a self-contained **statically linked** binary (no runtime dependencies):

```bash
docker compose exec sfrpc-go-test go build -buildvcs=false -o protoc-gen-sfrpc .
```
