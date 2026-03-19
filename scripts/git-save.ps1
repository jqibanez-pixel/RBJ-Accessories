param(
    [Parameter(Mandatory = $true)]
    [string]$Message
)

$ErrorActionPreference = 'Stop'

git rev-parse --is-inside-work-tree | Out-Null

$status = git status --short
if (-not $status) {
    Write-Output 'No changes to commit.'
    exit 0
}

git add -A
git commit -m $Message
git status --short --branch
