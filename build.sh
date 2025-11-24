#!/bin/bash

docker buildx build \
    --platform linux/amd64,linux/arm64 \
    -t brianlmoon/phlag:latest \
    -t brianlmoon/phlag:$1 \
    --push \
    .
