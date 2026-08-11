#!/usr/bin/env bash
set -Eeuo pipefail

IMAGE_URI="${1:?Image URI is required}"
CONTAINERS=(zentrum-web zentrum-worker zentrum-scheduler)

declare -A previous_images
temporary_directory="$(mktemp -d)"
deployment_started=false

cleanup() {
    sudo rm -rf "$temporary_directory"
}

start_container() {
    local name="$1"
    local image="$2"
    local command="$3"
    local environment_file="$temporary_directory/${name}.env"
    local arguments=(--detach --name "$name" --restart always --add-host host.docker.internal:host-gateway --env-file "$environment_file")

    if [[ "$name" == "zentrum-web" ]]; then
        arguments+=(--publish 127.0.0.1:8080:80)
    fi

    sudo docker run "${arguments[@]}" "$image" "$command" >/dev/null
}

rollback() {
    trap - ERR
    set +e

    if [[ "$deployment_started" != true ]]; then
        return
    fi

    echo "Deployment failed; restoring previous Lightsail containers." >&2

    for name in "${CONTAINERS[@]}"; do
        sudo docker rm --force "$name" >/dev/null 2>&1 || true
    done

    start_container zentrum-web "${previous_images[zentrum-web]}" web
    start_container zentrum-worker "${previous_images[zentrum-worker]}" worker
    start_container zentrum-scheduler "${previous_images[zentrum-scheduler]}" scheduler
}

trap rollback ERR
trap cleanup EXIT

for name in "${CONTAINERS[@]}"; do
    previous_images["$name"]="$(sudo docker inspect "$name" --format '{{.Config.Image}}')"
    sudo docker inspect "$name" --format '{{range .Config.Env}}{{println .}}{{end}}' > "$temporary_directory/${name}.env"
    chmod 600 "$temporary_directory/${name}.env"
done

sudo docker pull "$IMAGE_URI"

echo "Running database migrations."
sudo docker run --rm \
    --add-host host.docker.internal:host-gateway \
    --env-file "$temporary_directory/zentrum-web.env" \
    "$IMAGE_URI" php artisan migrate --force

echo "Checking the new image before switching production traffic."
sudo docker rm --force zentrum-web-candidate >/dev/null 2>&1 || true
sudo docker run --detach \
    --name zentrum-web-candidate \
    --add-host host.docker.internal:host-gateway \
    --env-file "$temporary_directory/zentrum-web.env" \
    --publish 127.0.0.1:8081:80 \
    "$IMAGE_URI" web >/dev/null

for _ in {1..30}; do
    if curl --fail --silent --show-error http://127.0.0.1:8081/up >/dev/null; then
        break
    fi

    sleep 2
done

curl --fail --silent --show-error http://127.0.0.1:8081/up >/dev/null
sudo docker rm --force zentrum-web-candidate >/dev/null

deployment_started=true
for name in "${CONTAINERS[@]}"; do
    sudo docker rm --force "$name" >/dev/null
done

start_container zentrum-web "$IMAGE_URI" web
start_container zentrum-worker "$IMAGE_URI" worker
start_container zentrum-scheduler "$IMAGE_URI" scheduler

for _ in {1..30}; do
    if curl --fail --silent --show-error http://127.0.0.1:8080/up >/dev/null; then
        deployment_started=false
        echo "Lightsail production deployment completed: $IMAGE_URI"
        exit 0
    fi

    sleep 2
done

curl --fail --silent --show-error http://127.0.0.1:8080/up >/dev/null
