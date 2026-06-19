# Run WP-CLI commands in the firmpilot-copilot environment.
# Usage: .\scripts\wp.ps1 plugin list
param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Args
)

if ($Args.Count -eq 0) {
    docker compose --profile tools run --rm wpcli --info
    exit $LASTEXITCODE
}

docker compose --profile tools run --rm wpcli @Args
exit $LASTEXITCODE
