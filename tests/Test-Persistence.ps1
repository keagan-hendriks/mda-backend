<#
MDA database persistence evidence test (PowerShell 5.1+).
Creates an isolated test user, medication, and intake log, and verifies POST/PUT via fresh GETs.
Leaves data in database for phpMyAdmin screenshots; delete it manually afterwards.

Run from backend root with XAMPP Apache + MariaDB running:
  powershell -ExecutionPolicy Bypass -File .\tests\Test-Persistence.ps1
For Docker later:
  powershell -ExecutionPolicy Bypass -File .\tests\Test-Persistence.ps1 -BaseUrl http://localhost:8080/api
#>
param([string]$BaseUrl = 'http://localhost/mda_backend/api')
$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$passed = 0
function Assert-Equal($actual, $expected, [string]$name) {
    if ($actual -ne $expected) { throw "FAIL $name : expected '$expected', got '$actual'" }
    $script:passed++
    Write-Host "[PASS] $name" -ForegroundColor Green
}
function Api([string]$url, [string]$method='GET', $payload=$null) {
    $args = @{ Uri="$BaseUrl/$url"; Method=$method; ErrorAction='Stop' }
    if ($null -ne $payload) {
        $args.ContentType = 'application/json'
        $args.Body = ($payload | ConvertTo-Json -Depth 10 -Compress)
    }
    $r = Invoke-RestMethod @args
    if ($r.success -ne $true) { throw "API failure: $method $url : $($r.message)" }
    return $r
}

try {
    Write-Host "=== MDA persistent database test ===" -ForegroundColor Cyan
    $id = [guid]::NewGuid().ToString('N').Substring(0,12)
    $email = "persistence-$id@example.invalid"
    $u = Api 'users/create.php' 'POST' @{fullName='Persistence Evidence User';email=$email;password='LocalEvidenceTest123!'}
    $uid = [int]$u.data.userId
    if ($uid -le 0) { throw 'Missing userId from registration response' }
    $userRead = Api "users/get.php?userId=$uid"
    Assert-Equal $userRead.data.email $email 'user persisted after create'

    $m = Api 'medications/create.php' 'POST' @{userId=$uid;medicationName="Evidence Medication $id";notes='Original note';isActive=$true}
    $mid = [int]$m.data.medicationId
    if ($mid -le 0) { throw 'Missing medicationId from create response' }
    $fresh = Api "medications/get.php?medicationId=$mid"
    Assert-Equal $fresh.data.medicationName "Evidence Medication $id" 'medication persisted after create'
    $null = Api 'medications/update.php' 'PUT' @{medicationId=$mid;medicationName="Evidence Medication Updated $id";notes='Updated note'}
    $fresh = Api "medications/get.php?medicationId=$mid"
    Assert-Equal $fresh.data.medicationName "Evidence Medication Updated $id" 'medication update persisted (fresh GET)'
    Assert-Equal $fresh.data.notes 'Updated note' 'medication notes persisted'

    $at = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
    $l = Api 'medication_logs/create.php' 'POST' @{medicationId=$mid;scheduledAt=$at;status='taken';notes='Original intake'}
    $lid = [int]$l.data.logId
    if ($lid -le 0) { throw 'Missing logId from create response' }
    $fresh = Api "medication_logs/get.php?logId=$lid"
    Assert-Equal $fresh.data.status 'taken' 'intake log persisted after create'
    $null = Api 'medication_logs/update.php' 'PUT' @{logId=$lid;status='skipped';notes='Corrected intake'}
    $fresh = Api "medication_logs/get.php?logId=$lid"
    Assert-Equal $fresh.data.status 'skipped' 'intake correction persisted (fresh GET)'
    Assert-Equal $fresh.data.notes 'Corrected intake' 'intake notes persisted'

    $meds = Api "medications/get.php?userId=$uid"
    Assert-Equal @($meds.data | Where-Object { $_.medicationId -eq $mid }).Count 1 'medication listed for test user'
    $logs = Api "medication_logs/get.php?userId=$uid"
    Assert-Equal @($logs.data | Where-Object { $_.logId -eq $lid }).Count 1 'intake listed for test user'

    Write-Host "`n[PASS] $passed persistence assertions" -ForegroundColor Green
    Write-Host "EVIDENCE IDs: userId=$uid medicationId=$mid logId=$lid" -ForegroundColor Yellow
    Write-Host "EVIDENCE email: $email" -ForegroundColor Yellow
    Write-Host 'Records intentionally retained for phpMyAdmin screenshots. Remove test user after evidence capture.' -ForegroundColor Yellow
} catch {
    Write-Host "[FAIL] $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
