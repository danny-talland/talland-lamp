param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$PhpArgs
)

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = (Resolve-Path (Join-Path $scriptDir "..\..")).Path
$currentDir = (Get-Location).Path

function Get-ContainerWorkdir {
    param(
        [string]$RootPath,
        [string]$LocationPath
    )

    $normalizedRoot = [System.IO.Path]::GetFullPath($RootPath).TrimEnd('\', '/')
    $normalizedLocation = [System.IO.Path]::GetFullPath($LocationPath).TrimEnd('\', '/')

    if (-not $normalizedLocation.StartsWith($normalizedRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        return "/var/www/html"
    }

    $relativePath = $normalizedLocation.Substring($normalizedRoot.Length).TrimStart('\', '/')

    if ([string]::IsNullOrWhiteSpace($relativePath)) {
        return "/var/www/html"
    }

    $segments = $relativePath -split '[\\/]'
    if ($segments[0] -eq 'projects') {
        return "/var/www/html/" + ($segments -join '/')
    }

    if ($segments[0] -eq 'www') {
        if ($segments.Length -eq 1) {
            return "/var/www/html"
        }

        return "/var/www/html/" + (($segments[1..($segments.Length - 1)]) -join '/')
    }

    return "/var/www/html"
}

$workdir = Get-ContainerWorkdir -RootPath $repoRoot -LocationPath $currentDir
$dockerArgs = @("compose", "exec", "-T", "-w", $workdir, "webserver", "php") + $PhpArgs

& docker @dockerArgs
exit $LASTEXITCODE
