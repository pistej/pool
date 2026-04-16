# Stage 1: Build the protoc-gen-sfrpc plugin and other tools
FROM golang:1.26-alpine AS builder

# Install buf and protoc-gen-go using go install in the builder stage
RUN go install github.com/bufbuild/buf/cmd/buf@v1.66.0 && \
    go install google.golang.org/protobuf/cmd/protoc-gen-go@latest

WORKDIR /src
# Source is relative to context (project root)
COPY tools/protoc-gen-sfrpc/ .
RUN go build -o /usr/local/bin/protoc-gen-sfrpc .


# Stage 2: Final development/testing image
FROM golang:1.26-alpine

ENV GOFLAGS="-buildvcs=false"

# Install essential tools and protoc
RUN apk add --no-cache \
    curl \
    git \
    make \
    protobuf-dev \
    protoc \
    ca-certificates

# Copy built tools and plugin from builder stage
COPY --from=builder /go/bin/buf /usr/local/bin/buf
COPY --from=builder /go/bin/protoc-gen-go /usr/local/bin/protoc-gen-go
COPY --from=builder /usr/local/bin/protoc-gen-sfrpc /usr/local/bin/protoc-gen-sfrpc

WORKDIR /var/www

CMD ["buf", "generate"]
