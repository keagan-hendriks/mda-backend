<#
Run in Windows PowerShell (XAMPP Apache + MySQL must be running):
  cd C:\xampp\htdocs\mda_backend
  powershell -ExecutionPolicy Bypass -File .\tests\Test-Crud.ps1

This script creates a disposable user through your existing users/create.php endpoint,
then exercises CRUD on all six resources. It removes the six test resources in finally,
but intentionally leaves the test user + default preferences for manual deletion.
#>
param(
    [string]$BaseUrl = 'http://localhost/mda_backend/api',
    [int]$ExistingUserId = 0
)
$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
$created = @{}
$userId = $ExistingUserId
$testEmail = $null
$passed = 0

function Assert-True([bool]$ok, [string]$message) {
    if (-not $ok) { throw "FAILED: $message" }
    Write-Host "[PASS] $message" -ForegroundColor Green
    $script:passed++
}

function Invoke-Api([string]$resource, [string]$action, [string]$method, $body = $null, [string]$query = '') {
    $url = "$BaseUrl/$resource/$action.php$query"
    $arguments = @{ Uri = $url; Method = $method; ErrorAction = 'Stop' }
    if ($null -ne $body) {
        $arguments['ContentType'] = 'application/json'
        $arguments['Body'] = ($body | ConvertTo-Json -Depth 10 -Compress)
    }
    $response = Invoke-RestMethod @arguments
    if ($response.success -ne $true) { throw "API returned success=false: $url - $($response.message)" }
    return $response
}

function Assert-NotFound([string]$resource, [string]$query) {
    try {
        $result = Invoke-RestMethod -Uri "$BaseUrl/$resource/get.php$query" -Method Get -ErrorAction Stop
        throw "Expected HTTP 404 but received a normal response for $resource$query"
    } catch {
        if ($null -ne $_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 404) {
            Assert-True $true "$resource deletion persisted (GET -> 404)"
        } else { throw }
    }
}

try {
    Write-Host '=== MDA full CRUD smoke test ===' -ForegroundColor Cyan
    if ($userId -le 0) {
        $suffix = [Guid]::NewGuid().ToString('N').Substring(0, 10)
        $testEmail = "crud-smoke-$suffix@example.invalid"
        $testUser = Invoke-Api 'users' 'create' 'Post' @{
            fullName = 'CRUD Smoke Test User'
            email = $testEmail
            password = 'LocalTestOnly123!'
        }
        $userId = [int]$testUser.data.userId
        Write-Host "Created disposable user: $userId ($testEmail)" -ForegroundColor Yellow
    } else {
        Write-Host "Using existing user: $userId" -ForegroundColor Yellow
    }

    $defaults = Invoke-Api 'preferences' 'get' 'Get' $null "?userId=$userId"
    Assert-True ($defaults.data.userId -eq $userId) 'User has a preferences row'

    # 1. BADGES
    $badgeName = "Smoke Badge $([Guid]::NewGuid().ToString('N').Substring(0,8))"
    $badge = Invoke-Api 'badges' 'create' 'Post' @{
        badgeName = $badgeName; description = 'Automated test badge'
        requirementDescription = 'Complete test'; pointsReward = 5; iconName = 'test'
    }
    $badgeId = [int]$badge.data.badgeId; $created.badgeId = $badgeId
    Assert-True ($badgeId -gt 0) 'Badges: CREATE'
    $read = Invoke-Api 'badges' 'get' 'Get' $null "?badgeId=$badgeId"
    Assert-True ($read.data.badgeName -eq $badgeName) 'Badges: READ one'
    $list = Invoke-Api 'badges' 'get' 'Get'
    Assert-True (@($list.data | Where-Object { $_.badgeId -eq $badgeId }).Count -eq 1) 'Badges: READ list'
    $updated = Invoke-Api 'badges' 'update' 'Put' @{ badgeId = $badgeId; pointsReward = 17 }
    Assert-True ($updated.data.pointsReward -eq 17) 'Badges: UPDATE'

    # 2. MEDICATIONS
    $med = Invoke-Api 'medications' 'create' 'Post' @{
        userId = $userId; medicationName = 'Smoke Medication'; notes = 'initial'; isActive = $true
    }
    $medId = [int]$med.data.medicationId; $created.medicationId = $medId
    Assert-True ($medId -gt 0) 'Medications: CREATE'
    $read = Invoke-Api 'medications' 'get' 'Get' $null "?medicationId=$medId"
    Assert-True ($read.data.medicationName -eq 'Smoke Medication') 'Medications: READ one'
    $list = Invoke-Api 'medications' 'get' 'Get' $null "?userId=$userId"
    Assert-True (@($list.data | Where-Object { $_.medicationId -eq $medId }).Count -eq 1) 'Medications: READ list'
    $updated = Invoke-Api 'medications' 'update' 'Put' @{ medicationId = $medId; isActive = $false; notes = $null }
    Assert-True (($updated.data.isActive -eq $false) -and ($null -eq $updated.data.notes)) 'Medications: UPDATE and clear nullable notes'

    # 3. REMINDERS
    $rem = Invoke-Api 'reminders' 'create' 'Post' @{
        medicationId = $medId; reminderTime = '08:30:00'; repeatDaily = $true; isEnabled = $true
    }
    $remId = [int]$rem.data.reminderId; $created.reminderId = $remId
    Assert-True ($remId -gt 0) 'Reminders: CREATE'
    $read = Invoke-Api 'reminders' 'get' 'Get' $null "?reminderId=$remId"
    Assert-True ($read.data.reminderTime -eq '08:30:00') 'Reminders: READ one'
    $list = Invoke-Api 'reminders' 'get' 'Get' $null "?userId=$userId"
    Assert-True (@($list.data | Where-Object { $_.reminderId -eq $remId }).Count -eq 1) 'Reminders: READ list'
    $updated = Invoke-Api 'reminders' 'update' 'Put' @{ reminderId = $remId; reminderTime = '09:15:00'; isEnabled = $false }
    Assert-True (($updated.data.reminderTime -eq '09:15:00') -and ($updated.data.isEnabled -eq $false)) 'Reminders: UPDATE'

    # 4. MEDICATION LOGS
    $scheduledAt = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
    $log = Invoke-Api 'medication_logs' 'create' 'Post' @{
        medicationId = $medId; reminderId = $remId; scheduledAt = $scheduledAt
        status = 'taken'; notes = 'initial'
    }
    $logId = [int]$log.data.logId; $created.logId = $logId
    Assert-True ($logId -gt 0) 'Medication logs: CREATE'
    $read = Invoke-Api 'medication_logs' 'get' 'Get' $null "?logId=$logId"
    Assert-True ($read.data.status -eq 'taken') 'Medication logs: READ one'
    $list = Invoke-Api 'medication_logs' 'get' 'Get' $null "?userId=$userId"
    Assert-True (@($list.data | Where-Object { $_.logId -eq $logId }).Count -eq 1) 'Medication logs: READ list'
    $updated = Invoke-Api 'medication_logs' 'update' 'Put' @{ logId = $logId; status = 'skipped' }
    Assert-True ($updated.data.status -eq 'skipped') 'Medication logs: UPDATE'

    # 5. USER BADGES (composite PK)
    $ub = Invoke-Api 'user_badges' 'create' 'Post' @{ userId = $userId; badgeId = $badgeId }
    $created.userBadge = $true
    Assert-True ($ub.data.userId -eq $userId -and $ub.data.badgeId -eq $badgeId) 'User badges: CREATE'
    $read = Invoke-Api 'user_badges' 'get' 'Get' $null "?userId=$userId&badgeId=$badgeId"
    Assert-True ($read.data.badgeId -eq $badgeId) 'User badges: READ one'
    $list = Invoke-Api 'user_badges' 'get' 'Get' $null "?userId=$userId"
    Assert-True (@($list.data | Where-Object { $_.badgeId -eq $badgeId }).Count -eq 1) 'User badges: READ list'
    $updated = Invoke-Api 'user_badges' 'update' 'Put' @{
        userId = $userId; badgeId = $badgeId; earnedAt = '2026-10-19 09:00:00'
    }
    Assert-True ($updated.data.earnedAt -eq '2026-10-19 09:00:00') 'User badges: UPDATE earnedAt'

    # 6. USER REWARDS (PK=userId)
    $rewards = Invoke-Api 'user_rewards' 'create' 'Post' @{
        userId = $userId; totalPoints = 0; currentStreak = 0; longestStreak = 0
    }
    $created.userRewards = $true
    Assert-True ($rewards.data.userId -eq $userId) 'User rewards: CREATE'
    $read = Invoke-Api 'user_rewards' 'get' 'Get' $null "?userId=$userId"
    Assert-True ($read.data.totalPoints -eq 0) 'User rewards: READ one'
    $list = Invoke-Api 'user_rewards' 'get' 'Get' $null "?userId=$userId&list=1"
    Assert-True (@($list.data | Where-Object { $_.userId -eq $userId }).Count -eq 1) 'User rewards: READ list'
    $updated = Invoke-Api 'user_rewards' 'update' 'Put' @{
        userId = $userId; totalPoints = 25; currentStreak = 2; longestStreak = 2
    }
    Assert-True (($updated.data.totalPoints -eq 25) -and ($updated.data.currentStreak -eq 2)) 'User rewards: UPDATE'

    # DELETE in reverse dependency order.
    Invoke-Api 'medication_logs' 'delete' 'Delete' $null "?logId=$logId" | Out-Null
    $created.Remove('logId'); Assert-NotFound 'medication_logs' "?logId=$logId"
    Invoke-Api 'reminders' 'delete' 'Delete' $null "?reminderId=$remId" | Out-Null
    $created.Remove('reminderId'); Assert-NotFound 'reminders' "?reminderId=$remId"
    Invoke-Api 'medications' 'delete' 'Delete' $null "?medicationId=$medId" | Out-Null
    $created.Remove('medicationId'); Assert-NotFound 'medications' "?medicationId=$medId"
    Invoke-Api 'user_badges' 'delete' 'Delete' $null "?userId=$userId&badgeId=$badgeId" | Out-Null
    $created.Remove('userBadge'); Assert-NotFound 'user_badges' "?userId=$userId&badgeId=$badgeId"
    Invoke-Api 'user_rewards' 'delete' 'Delete' $null "?userId=$userId" | Out-Null
    $created.Remove('userRewards'); Assert-NotFound 'user_rewards' "?userId=$userId"
    Invoke-Api 'badges' 'delete' 'Delete' $null "?badgeId=$badgeId" | Out-Null
    $created.Remove('badgeId'); Assert-NotFound 'badges' "?badgeId=$badgeId"

    Write-Host "`nAll $passed checks passed. Six resources were created, read, updated and deleted." -ForegroundColor Green
    if ($testEmail) { Write-Host "Test user left for manual cleanup: $userId ($testEmail)" -ForegroundColor Yellow }
} catch {
    Write-Host "`nCRUD smoke test failed: $_" -ForegroundColor Red
    throw
} finally {
    # Best-effort cleanup when a test fails midway.
    $cleanup = @(
        @('logId', 'medication_logs', "?logId=$($created.logId)"),
        @('reminderId', 'reminders', "?reminderId=$($created.reminderId)"),
        @('medicationId', 'medications', "?medicationId=$($created.medicationId)"),
        @('userBadge', 'user_badges', "?userId=$userId&badgeId=$($created.badgeId)"),
        @('userRewards', 'user_rewards', "?userId=$userId"),
        @('badgeId', 'badges', "?badgeId=$($created.badgeId)")
    )
    foreach ($item in $cleanup) {
        if ($created.ContainsKey($item[0])) {
            try { Invoke-Api $item[1] 'delete' 'Delete' $null $item[2] | Out-Null }
            catch { Write-Warning "Manual cleanup may be needed for $($item[1])" }
        }
    }
}
