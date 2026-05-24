param(
    [switch] $DryRun
)

$script = Join-Path $PSScriptRoot "package-release.php"
$args = @($script)

if ($DryRun) {
    $args += "--dry-run"
}

php @args
