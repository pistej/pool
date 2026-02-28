FROM golang:1.21-alpine

# Install buf, protoc, and basic compile tools
RUN apk add --no-cache curl git make \
    && curl -sSL \
    "https://github.com/bufbuild/buf/releases/download/v1.30.0/buf-Linux-x86_64" \
    -o /usr/local/bin/buf \
    && chmod +x /usr/local/bin/buf

# Set working directory for protoc plugin
WORKDIR /app/tools/protoc-gen-sfrpc
COPY tools/protoc-gen-sfrpc /app/tools/protoc-gen-sfrpc
RUN go install google.golang.org/protobuf/cmd/protoc-gen-go@latest \
    && go build -o /usr/local/bin/protoc-gen-sfrpc .

WORKDIR /var/www
