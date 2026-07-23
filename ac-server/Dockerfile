FROM --platform=linux/amd64 debian:bookworm-slim

# acServer is a 32-bit Linux binary
RUN dpkg --add-architecture i386 \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        lib32gcc-s1 \
        lib32stdc++6 \
        zlib1g:i386 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /ac-server

# Drop the acServer binary and its system/content folders into ac-server/ before building
COPY . .
RUN chmod +x acServer entrypoint.sh

VOLUME ["/ac-server/cfg"]

EXPOSE 9600/tcp 9600/udp 8081/tcp

ENTRYPOINT ["./entrypoint.sh"]
