# protoc-gen-sfrpc

A custom gRPC code generator for PHP, specifically designed to work with the `sfrpc/pool` Symfony bundle. It generates PHP Interfaces, concrete Clients, and Proxy wrappers that leverage the connection pool.

## Features

- **Standard Interfaces**: Emits PSR-compliant client interfaces.
- **Concrete Clients**: Native HTTP/2 gRPC clients using the `sfrpc/pool` `BaseClient`.
- **Pool Proxies**: Generates Proxy classes that automatically `borrow()` and `return()` connections from the `ConnectionPool` for every request, ensuring coroutine safety and efficient resource usage.

## Architecture

- `main.go`: The plugin entry point.
- `generator/`: Contains the core PHP generation logic (`php.go`).
- `tests/`: Integration tests ensuring correct PHP code emission.
- `buf.gen.yaml`: Configuration for the `buf` CLI.

## Development & Usage

All development tasks are performed within the `sfrpc-go-test` Docker container.

### Building the environment

```bash
docker compose build sfrpc-go-test
docker compose up -d sfrpc-go-test
```

### Generating PHP Code

To regenerate PHP classes from your `.proto` files (e.g., `test.proto`), run:

```bash
docker compose exec sfrpc-go-test buf generate
```

> [!TIP]
> The `buf.gen.yaml` is configured to use `go run main.go`. This means any changes you make to the `.go` generator source files are applied **instantly** when you run `buf generate`, without needing to recompile the binary or rebuild the Docker image.

### Running Tests

To run the Go plugin's internal tests:

```bash
docker compose exec sfrpc-go-test go test -v ./...
```

### Manual Compilation (Optional)

If you need to produce a static binary for use outside of the dynamic `buf` workflow:

```bash
docker compose exec sfrpc-go-test go build -o protoc-gen-sfrpc main.go
```
