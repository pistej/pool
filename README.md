# Swoole gRPC Pool Bundle

A high-performance, **zero-dependency** Symfony bundle that provides a connection pool for Swoole HTTP/2 gRPC clients.

## Features

- **Zero External Dependencies**: Built natively on pure PHP 8.3 and Swoole Extensions. No reliance on heavy 3rd-party gRPC or connection pool libraries.
- **Strict Typing**: Fully utilizes PHP 8.3 strict types, enums, and readonly properties.
- **Swoole Connection Pool**: Autonomously manages connection multiplexing, active connection limits, starvation/wait timeouts, and background idle connection reaping via Swoole Channels and Timers.
- **Seamless Symfony Integration**: Exposes semantic `config/packages/sfrpc_pool.yaml` configurations. Automatically boots connection pools using Symfony Events (like `WorkerStartedEvent`) and wires up dependent services.
- **Go Code Generator**: Includes a dedicated `protoc-gen-sfrpc` Go plugin compatible with `buf` to generate perfectly typed PHP Interfaces, concrete Client DTOs, and intelligent Proxy wrappers.
- **Developer Experience**: You typehint generated `*ClientInterface` classes in your Symfony controllers. Under the hood, the Proxy wrapper automatically borrows an active HTTP/2 connection from the Swoole pool, executes your RPC, and returns the connection to the pool instantly.

## Architecture Highlights

1. **`Sfrpc\Pool\Grpc\BaseClient`**: Handles the low-level protobuf byte-stream length-prefixed framing and Swoole HTTP/2 request/response execution.
2. **`Sfrpc\Pool\ConnectionPool\ConnectionPool`**: Core pool orchestration using `Swoole\Coroutine\Channel` to coordinate concurrent borrowing and gracefully expanding connections up to `maxActive`.
3. **`SfrpcPoolExtension`**: Symfony compiler pass that dynamically constructs service definitions for pools and injects them into generated proxies.
4. **`tools/protoc-gen-sfrpc`**: The Go compiler plugin that processes `.proto` files into PHP files.
5. **`Sfrpc\Pool\SfrpcPoolBundle`**: The Symfony bundle class that hooks everything together.

## Configuration

### 1. Register the bundle

Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Sfrpc\Pool\SfrpcPoolBundle::class => ['all' => true],
];
```

### 2. Configure the pools

Create `config/packages/sfrpc_pool.yaml`:

```yaml
sfrpc_pool:
    # Optional: The event to listen for to initialize pools automatically. 
    worker_started_event: 'App\Event\MySwooleWorkerStartedEvent'
    # if using symfony-swoole/swoole-bundle, event NAME can be used
#    worker_started_event: !php/const SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStartedEvent::NAME
    # Optional: Events to close pools automatically on worker lifecycle shutdown paths
    worker_stop_event: !php/const SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerStopEvent::NAME
    worker_exit_event: !php/const SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerExitEvent::NAME
    worker_error_event: !php/const SwooleBundle\SwooleBundle\Bridge\Symfony\Event\WorkerErrorEvent::NAME

    pools:
        default:
            host: '%env(GRPC_HOST)%'
            port: '%env(int:GRPC_PORT)%'
            ssl: false
            min_active: 2
            max_active: 10
            max_wait_time: 5.0      # Seconds to wait for a free connection
            max_idle_time: 60.0     # Max time a connection can stay idle
            idle_check_interval: 30.0 # How often to check for idle connections
            debug_logs: '%kernel.debug%' # Optional: Enable detailed pool operation logs (default: false),
            swoole_settings:
                # Optional: Swoole-specific client settings
                timeout: 5.0
                package_max_length: 2097152
            proxies:
                # List of Proxy classes that should use this pool
                - 'App\Grpc\Proxy\GreeterProxy'
```

### 3. Usage

After generating your gRPC clients using our Go tool, simply typehint the **Interface** in your services:

```php
use App\Grpc\GreeterInterface;

class MyService
{
    public function __construct(
        private GreeterInterface $greeter
    ) {}

    public function doSomething()
    {
        $response = $this->greeter->SayHello(new HelloRequest(['name' => 'World']));
        // Behind the scenes, the GreeterProxy borrowed a connection from the 'default' pool
    }
}
```

## Commands

Use the predefined docker-compose environment to run the test suite and verify everything is working safely.

### Start the environment
```bash
docker compose up -d
```

### Run PHP Unit and Integration Tests
```bash
docker compose exec swoole-php vendor/bin/phpunit
```

*Note: The included `.docker/Dockerfile` automatically installs the `pcov` extension during `docker compose up -d`, so code coverage works out-of-the-box.*

### Run Static Analysis (PHPStan)
```bash
docker compose exec swoole-php vendor/bin/phpstan analyse
```

### Run PHP Code Sniffer (Code Style)
```bash
docker compose exec swoole-php vendor/bin/phpcs
```

### Fix Code Style (PHPCBF)
```bash
docker compose exec swoole-php vendor/bin/phpcbf
```

### Run Go Generator Unit Tests

We use a standalone higher-version Golang container (`sfrpc-go-test`) to run the toolchain tests and generate code.

#### Build the Generator Image
If you modify the Dockerfile or need to build it for the first time:
```bash
docker compose build sfrpc-go-test
```

#### Run tests
```bash
docker compose exec sfrpc-go-test go test -v ./...
```

*Note: If you add new `.proto` files with new dependencies, you might need to run `go mod tidy` in the container.*

#### Generate PHP Code from Proto
```bash
docker compose exec sfrpc-go-test buf generate
```


## TODO

Look in rest-connection and plugins for inspiration

- [ ] Add support for array cache
- [ ] Add support for sentry_tracing
- [ ] Add support for slower retry if host/port is not available
- [ ] Add support for 
