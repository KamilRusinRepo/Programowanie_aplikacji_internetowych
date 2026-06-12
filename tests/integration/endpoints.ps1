param(
    [string]$BaseUrl = $env:APP_TEST_BASE_URL
)

if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    $BaseUrl = "http://localhost:8080"
}

$ErrorActionPreference = "Stop"

function Assert-PageContains {
    param(
        [string]$Path,
        [string]$ExpectedText
    )

    $url = "$BaseUrl$Path"
    $response = Invoke-WebRequest -Uri $url -UseBasicParsing

    if ($response.StatusCode -ne 200) {
        throw "Expected 200 for $Path, got $($response.StatusCode)."
    }

    if ($response.Content -notlike "*$ExpectedText*") {
        throw "Expected $Path to contain '$ExpectedText'."
    }

    Write-Host "[OK] $Path"
}

Assert-PageContains "/" "FlashMind"
Assert-PageContains "/login" "Sign In"
Assert-PageContains "/register" "Sign Up"
Assert-PageContains "/explore" "Explore Marketplace"

Write-Host "Integration endpoint checks passed for $BaseUrl"
