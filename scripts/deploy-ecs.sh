#!/usr/bin/env bash
set -Eeuo pipefail

required=(AWS_REGION ECS_CLUSTER ECS_WEB_SERVICE ECS_WORKER_SERVICE ECS_SCHEDULER_SERVICE IMAGE_URI HEALTHCHECK_URL)
for name in "${required[@]}"; do
    if [[ -z "${!name:-}" ]]; then
        echo "Missing required environment variable: ${name}" >&2
        exit 1
    fi
done

declare -A previous_tasks

task_family() {
    aws ecs describe-services \
        --cluster "$ECS_CLUSTER" \
        --services "$1" \
        --query 'services[0].taskDefinition' \
        --output text
}

register_release() {
    local service="$1"
    local current output
    current="$(task_family "$service")"
    previous_tasks["$service"]="$current"

    output="$(mktemp)"
    aws ecs describe-task-definition --task-definition "$current" --query taskDefinition > "$output"
    jq --arg image "$IMAGE_URI" '
        .containerDefinitions[0].image = $image
        | del(.taskDefinitionArn, .revision, .status, .requiresAttributes, .compatibilities, .registeredAt, .registeredBy)
    ' "$output" > "${output}.new"

    aws ecs register-task-definition \
        --cli-input-json "file://${output}.new" \
        --query 'taskDefinition.taskDefinitionArn' \
        --output text
}

run_migrations() {
    local task_definition="$1"
    local network
    network="$(aws ecs describe-services --cluster "$ECS_CLUSTER" --services "$ECS_WEB_SERVICE" --query 'services[0].networkConfiguration' --output json)"

    local task_arn
    task_arn="$(aws ecs run-task \
        --cluster "$ECS_CLUSTER" \
        --launch-type FARGATE \
        --task-definition "$task_definition" \
        --network-configuration "$network" \
        --overrides '{"containerOverrides":[{"name":"app","command":["php","artisan","migrate","--force"]}]}' \
        --query 'tasks[0].taskArn' \
        --output text)"

    aws ecs wait tasks-stopped --cluster "$ECS_CLUSTER" --tasks "$task_arn"
    local exit_code
    exit_code="$(aws ecs describe-tasks --cluster "$ECS_CLUSTER" --tasks "$task_arn" --query 'tasks[0].containers[0].exitCode' --output text)"
    [[ "$exit_code" = "0" ]]
}

rollback() {
    echo "Deployment failed; restoring previous task definitions." >&2
    for service in "$ECS_WEB_SERVICE" "$ECS_WORKER_SERVICE" "$ECS_SCHEDULER_SERVICE"; do
        if [[ -n "${previous_tasks[$service]:-}" ]]; then
            aws ecs update-service --cluster "$ECS_CLUSTER" --service "$service" --task-definition "${previous_tasks[$service]}" >/dev/null
        fi
    done
}
trap rollback ERR

web_task="$(register_release "$ECS_WEB_SERVICE")"
worker_task="$(register_release "$ECS_WORKER_SERVICE")"
scheduler_task="$(register_release "$ECS_SCHEDULER_SERVICE")"

run_migrations "$web_task"

aws ecs update-service --cluster "$ECS_CLUSTER" --service "$ECS_WEB_SERVICE" --task-definition "$web_task" >/dev/null
aws ecs update-service --cluster "$ECS_CLUSTER" --service "$ECS_WORKER_SERVICE" --task-definition "$worker_task" >/dev/null
aws ecs update-service --cluster "$ECS_CLUSTER" --service "$ECS_SCHEDULER_SERVICE" --task-definition "$scheduler_task" >/dev/null

aws ecs wait services-stable --cluster "$ECS_CLUSTER" --services "$ECS_WEB_SERVICE" "$ECS_WORKER_SERVICE" "$ECS_SCHEDULER_SERVICE"
curl --fail --show-error --silent --retry 6 --retry-delay 10 "${HEALTHCHECK_URL%/}/up" >/dev/null

trap - ERR
echo "Production deployment completed: ${IMAGE_URI}"
