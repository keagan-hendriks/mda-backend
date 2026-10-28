<#
Non-destructive HTTP/API validation tests. Tests failure responses and input validation.
Note to team: it's meant to fail, tetsing failure messages
Run: powershell -ExecutionPolicy Bypass -File .\tests\Test-Api-Negative.ps1
#>
param([string]$BaseUrl = 'http://localhost/mda_backend/api')
$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$pass = 0
function Expect-Status([string]$name,[string]$path,[string]$method,[int]$expected,$body=$null) {
    $args = @{ Uri="$BaseUrl/$path"; Method=$method; ErrorAction='Stop'; UseBasicParsing=$true }
    if ($null -ne $body) {
        $args.ContentType = 'application/json'
        $args.Body = ($body | ConvertTo-Json -Depth 10 -Compress)
    }
    $actual = 0
    try {
        $r = Invoke-WebRequest @args
        $actual = [int]$r.StatusCode
    } catch {
        if ($null -eq $_.Exception.Response) { throw "Transport failure for $name : $($_.Exception.Message)" }
        $actual = [int]$_.Exception.Response.StatusCode
    }
    if ($actual -ne $expected) { throw "FAIL $name : expected HTTP $expected, received $actual" }
    $script:pass++
    Write-Host "[PASS] $name (HTTP $expected)" -ForegroundColor Green
}
try {
    Write-Host '=== MDA negative HTTP tests ===' -ForegroundColor Cyan
    Expect-Status 'health endpoint available' 'health.php' 'GET' 200
    Expect-Status 'users/create rejects GET' 'users/create.php' 'GET' 405
    Expect-Status 'medications/create rejects GET' 'medications/create.php' 'GET' 405
    Expect-Status 'medications/get requires userId for listing' 'medications/get.php' 'GET' 400
    Expect-Status 'medication_logs/get requires userId for listing' 'medication_logs/get.php' 'GET' 400
    Expect-Status 'invalid medication ID not found' 'medications/get.php?medicationId=2147483647' 'GET' 404
    Expect-Status 'medication create rejects blank name' 'medications/create.php' 'POST' 400 @{userId=1;medicationName=' ';isActive=$true}
    Expect-Status 'medication create rejects non-boolean isActive' 'medications/create.php' 'POST' 400 @{userId=1;medicationName='Test';isActive='true'}
    Expect-Status 'log create rejects invalid status' 'medication_logs/create.php' 'POST' 400 @{medicationId=1;scheduledAt='2026-10-28 10:00:00';status='unknown'}
    Expect-Status 'log create rejects invalid datetime' 'medication_logs/create.php' 'POST' 400 @{medicationId=1;scheduledAt='2026-02-30 09:00:00';status='taken'}
    Write-Host "`n[PASS] $pass HTTP/validation assertions" -ForegroundColor Green
} catch {
    Write-Host "[FAIL] $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
