param(
    [int] $Port = 8000
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command adb -ErrorAction SilentlyContinue)) {
    throw 'ADB belum tersedia di PATH.'
}

if (-not (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)) {
    throw "Backend Laravel belum aktif di port $Port."
}

# Emulator memakai jalur ini agar 127.0.0.1 mengarah ke Laravel di komputer.
adb reverse "tcp:$Port" "tcp:$Port" | Out-Null

$activeReverse = adb reverse --list | Select-String -SimpleMatch "tcp:$Port tcp:$Port"
if (-not $activeReverse) {
    throw "Koneksi emulator ke backend port $Port belum berhasil."
}

Write-Host "Android sudah terhubung ke backend Laravel pada port $Port."
