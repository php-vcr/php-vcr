#!/usr/bin/env bash

docker run --rm --name apache-web-server -p 8080:80 -v "$PWD/../../../":/var/www/html thecodingmachine/php:7.2-v1-apache
