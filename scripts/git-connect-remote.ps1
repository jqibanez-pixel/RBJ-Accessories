param(
    [Parameter(Mandatory = $true)]
    [string]$RemoteUrl
)

$ErrorActionPreference = 'Stop'

git rev-parse --is-inside-work-tree | Out-Null

$existingOrigin = git remote get-url origin 2>$null

if ($LASTEXITCODE -eq 0 -and $existingOrigin) {
    git remote set-url origin $RemoteUrl
    Write-Output "Updated origin to $RemoteUrl"
} else {
    git remote add origin $RemoteUrl
    Write-Output "Added origin $RemoteUrl"
}

git remote -v
