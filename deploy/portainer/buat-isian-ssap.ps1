<#
.SYNOPSIS
  (LAPTOP) Membuat "isian" untuk stack SSAP Safety di Portainer, lalu menyalinnya ke clipboard.
.DESCRIPTION
  Nilai wajib: DB_PASSWORD, APP_KEY, PORTAL_SSO_SECRET. Bila berkas -OutFile sudah ada, nilainya DIPAKAI ULANG
  (volume database hanya menerima kata sandi yang dipakai pertama kali). Pakai -Baru hanya untuk database yang belum pernah dibuat.
.EXAMPLE
  powershell -ExecutionPolicy Bypass -File deploy\portainer\buat-isian-ssap.ps1
#>
param(
  [string]$Domain = 'lameruru.web.id',
  [int]$Port = 8112,
  [int]$DbHostPort = 3314,
  [string]$Tag = 'latest',
  [string]$KodeModul = 'ssap-safety',          # KODE modul ini di Portal (Modul > kode)
  [string]$SsoSecret,
  [string]$OutFile = (Join-Path $env:USERPROFILE 'akp-ssap-safety-isian.txt'),
  [switch]$Baru,
  [switch]$NoClipboard
)
$ErrorActionPreference = 'Stop'
function Rand($n) {
  $rng = [Security.Cryptography.RNGCryptoServiceProvider]::new()
  $b = New-Object byte[] $n; $rng.GetBytes($b); return , $b
}
$prev = @{}
if (-not $Baru -and (Test-Path $OutFile)) {
  Get-Content $OutFile | ForEach-Object { if ($_ -match '^([A-Z0-9_]+)=(.*)$') { $prev[$matches[1]] = $matches[2] } }
}
$dbpw = $prev['DB_PASSWORD']; $key = $prev['APP_KEY']
if (-not $dbpw) { $dbpw = ((Rand 18) | ForEach-Object { $_.ToString('x2') }) -join '' ; Write-Host '  [OK] Kata sandi database dibuat baru' -ForegroundColor Green } else { Write-Host '  [OK] Kata sandi database dipakai ulang' -ForegroundColor Green }
if (-not $key)  { $key = 'base64:' + [Convert]::ToBase64String((Rand 32)); Write-Host '  [OK] APP_KEY dibuat baru' -ForegroundColor Green } else { Write-Host '  [OK] APP_KEY dipakai ulang' -ForegroundColor Green }

$sso = $SsoSecret
if (-not $sso -and $prev['PORTAL_SSO_SECRET']) { $sso = $prev['PORTAL_SSO_SECRET']; Write-Host '  [OK] Rahasia SSO dipakai ulang' -ForegroundColor Green }
if (-not $sso) {
  Write-Host '  Salin rahasia SSO dari Portal > Modul > SSAP Safety > Integrasi, lalu tempel di bawah.' -ForegroundColor Yellow
  $sec = Read-Host '  Rahasia SSO modul SSAP Safety (tidak tampil saat diketik)' -AsSecureString
  $sso = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
}
if (-not $sso -or $sso -match '\s' -or $sso.Length -lt 32) { Write-Host '  [GAGAL] Rahasia SSO kosong, memuat spasi, atau kurang dari 32 karakter.' -ForegroundColor Red; exit 1 }

$rootpw = $prev['DB_ROOT_PASSWORD']
if (-not $rootpw) { $rootpw = ((Rand 18) | ForEach-Object { $_.ToString('x2') }) -join '' }

# Isian lengkap: rahasia (acak) + semua pengaturan lain dengan nilai yang dipakai.
$text = @(
  "DB_PASSWORD=$dbpw",
  "DB_ROOT_PASSWORD=$rootpw",
  "APP_KEY=$key",
  "PORTAL_SSO_SECRET=$sso",
  "PORTAL_APP_CODE=$KodeModul",
  "PORTAL_URL=https://portal.$Domain",
  "SSAP_URL=https://ssap.$Domain",
  "SSAP_PORT=$Port",
  "DB_HOST_PORT=$DbHostPort",
  "SSAP_TAG=$Tag",
  "SESSION_SECURE_COOKIE=true"
) -join "`r`n"
$text += "`r`n"
[IO.File]::WriteAllText($OutFile, $text, (New-Object Text.UTF8Encoding $false))
if (-not $NoClipboard) { Set-Clipboard -Value $text; Write-Host '  [OK] Isian disalin ke CLIPBOARD' -ForegroundColor Green }
Write-Host "  [OK] Salinan disimpan di: $OutFile"
Write-Host ''
Write-Host '  Portainer > Add stack > nama: ssap-safety > Repository > Environment variables > Advanced mode > Ctrl+V > Deploy.'
Write-Host "  Setelah selesai simpan kata sandinya di pengelola kata sandi, lalu: Remove-Item `"$OutFile`""
