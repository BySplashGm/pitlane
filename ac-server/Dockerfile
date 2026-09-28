# syntax=docker/dockerfile:1
#
# Builds the Assetto Corsa dedicated server by fetching it with SteamCMD at build time, so the repo
# and image never ship Kunos/505's proprietary files. See docs/decisions/steamcmd-ac-server-build.md
# for the validation history. AppID 302550 (Assetto Corsa Dedicated Server) rejects anonymous access
# ("No subscription") — a Steam account that owns Assetto Corsa is required; STEAM_USER/STEAM_PASSWORD
# are not optional in practice, only in Dockerfile syntax.
#
# Credentials are passed as BuildKit secrets, never as --build-arg — a --build-arg value gets
# substituted as literal Dockerfile/shell text, so special characters in a password (`, $, ", ...)
# can break or hijack the RUN command. Export them as env vars, then:
#   docker build -f ac-server/Dockerfile \
#     --secret id=steam_user,env=STEAM_USER --secret id=steam_password,env=STEAM_PASSWORD \
#     -t ac-server:latest ac-server
# `castor build` does this automatically when STEAM_USER/STEAM_PASSWORD are set in the environment.
#
# acServer is a 32-bit x86 Linux binary, so the image is pinned to linux/amd64 (emulated on arm64).
#
# This image only ever contains the dedicated-server binary, never game content: base tracks/cars
# require Assetto Corsa ownership too, separately from the server binary, and cannot be baked in for
# the same legal reason. Content is mounted at container runtime instead, from an operator-managed
# host directory (`AC_CONTENT_DIR`, see `castor content:seed` and `AcContentServiceInterface`) — this
# Dockerfile has no COPY/RUN step for it and never will.
FROM --platform=linux/amd64 debian:bookworm-slim

# SteamCMD and the 32-bit Linux acServer both need i386 runtime libraries.
RUN dpkg --add-architecture i386 \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        lib32gcc-s1 \
        lib32stdc++6 \
        zlib1g:i386 \
    && rm -rf /var/lib/apt/lists/*

# SteamCMD is pulled from Valve's CDN at build time — never committed.
RUN mkdir -p /steamcmd \
    && curl -sqL "https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz" \
       | tar zxf - -C /steamcmd

WORKDIR /ac-server

# Anonymous access to AppID 302550 is confirmed rejected ("No subscription"), so an account that owns
# Assetto Corsa is required. Credentials are read from BuildKit secret mounts (files, not Dockerfile
# ARG/ENV substitution) so raw password bytes are never spliced into a shell command string, never
# land in an image layer or the build cache, and special shell characters in the password can't be
# reinterpreted by /bin/sh.
#
# No platform override (@sSteamCmdForcePlatformType) is set: this runs on a genuine Linux host (the
# container itself), which auto-selects the matching platform. AppID 302550's depot bundles both the
# native Linux acServer (ELF, statically-linked Go binary) and Windows-only files together regardless
# of platform — confirmed by inspecting a real download — so the plain Linux default is enough.
RUN --mount=type=secret,id=steam_user,required=false \
    --mount=type=secret,id=steam_password,required=false \
    STEAM_USER="$(cat /run/secrets/steam_user 2>/dev/null || true)"; \
    STEAM_USER="${STEAM_USER:-anonymous}"; \
    if [ -s /run/secrets/steam_password ]; then \
        /steamcmd/steamcmd.sh \
            +force_install_dir /ac-server \
            +login "$STEAM_USER" "$(cat /run/secrets/steam_password)" \
            +app_update 302550 validate \
            +quit; \
    else \
        /steamcmd/steamcmd.sh \
            +force_install_dir /ac-server \
            +login "$STEAM_USER" \
            +app_update 302550 validate \
            +quit; \
    fi

# The depot bundles Windows-only files alongside the Linux ones this image actually runs; drop them
# rather than ship dead weight.
RUN rm -f acServer.exe acServer.bat acServerManager.exe acServerManager.pdb

COPY entrypoint.sh ./entrypoint.sh
RUN chmod +x ./entrypoint.sh \
    && if [ -f ./acServer ]; then chmod +x ./acServer; fi

# DockerService binds each server's cfg read-only here, matching acServer's working directory.
VOLUME ["/ac-server/cfg"]

EXPOSE 9600/tcp 9600/udp 8081/tcp

ENTRYPOINT ["./entrypoint.sh"]
