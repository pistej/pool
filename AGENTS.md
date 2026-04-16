# AI Agent Context

Dear AI Agent / LLM,

Welcome to the `sfrpc/pool` Symfony Bundle. This file contains critical architectural context to help you navigate, fix, or expand this codebase without breaking its core tenets.

## Core Tenets

1. **Zero External Dependencies**: We specifically engineered this package to *not* rely on external, heavy PHP gRPC or connection pool libraries (e.g. `bardiz12/swoole-grpc`, `open-smf/connection-pool`). If you need to add a feature, you **must build it natively** using PHP 8.3 and the `Swoole` extension. Do not run `composer require` for functionality unless explicitly approved by the user.
2. **Strict PHP 8.3 Typing**: All PHP code must use `declare(strict_types=1);` and leverage PHP 8.3 features extensively (readonly classes/properties, strict unions, enums, proper docblocks).
3. **Swoole Coroutine Safety**: This codebase runs entirely inside Swoole Coroutine contexts.
    - Never use standard blocking PHP calls (e.g., `sleep()`, `file_get_contents()` without coroutine hooks). 
    - Always use `Swoole\Coroutine\System` or equivalent non-blocking equivalents natively. 
    - Be mindful of Coroutine leaks; all `Swoole\Timer` ticks must be explicitly cleared using `$pool->close()` upon termination.
4. **Symfony Autowiring**: The `SfrpcPoolExtension` handles the heavy lifting. If you modify how connection pools are configured, ensure you parse it via `Configuration.php`, build the `Definition` cleanly in the extension, and map it to the generated Proxies.

## Project Structure

- `src/ConnectionPool/`: Contains the pure PHP generic connection pool. Uses `Swoole\Coroutine\Channel` for tracking connections cleanly via `ConnectionWrapper`.
- `src/Grpc/`: Contains the low-level HTTP/2 client (`BaseClient`) responsible for executing gRPC `POST` requests and packing/unpacking `google/protobuf` string structures into byte frames natively.
- `src/Proxy/`: Contains the base wrapper `AbstractGrpcProxy`. The generated PHP Code intercepts calls here to inject `$pool->borrow()` logic seamlessly.
- `src/EventSubscriber/`: Integration logic (`PoolLifecycleSubscriber`) that mounts the ConnectionPool boot phase to standard configurable Symfony events.
- `tools/protoc-gen-sfrpc/`: The Go plugin. It uses `protogen` to parse `.proto` files and emit standard PHP interfaces, exact concrete clients, and the Proxy client classes.

## Development Environment & Scripts

We use Docker to test due to the PHP Swoole and Go requirements.

```bash
# Run all tests natively:
docker compose up -d

# PHP Backend:
docker compose exec swoole-php vendor/bin/phpunit
docker compose exec swoole-php vendor/bin/phpstan analyse
# If PHPStan crashes on memory in this container, retry with:
docker compose exec swoole-php vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec swoole-php vendor/bin/phpcs

# Go Plugin:
docker compose exec sfrpc-go-test go test -v ./...
```

If you see PHPStan errors regarding `Swoole\Coroutine\...`, note that `swoole/ide-helper` does not correctly map all internal properties (like `$request->data` or `$client->errCode`). We have explicitly ignored unresolvable IDE warnings in `phpstan.neon`. Maintain those cleanly.
