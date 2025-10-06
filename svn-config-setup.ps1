# svn-config-setup.ps1

$configPath = Join-Path $env:APPDATA "Subversion\config"

if (Test-Path $configPath) {
    Write-Host "SVN config found at: $configPath" -ForegroundColor Green

    $content = Get-Content $configPath -Raw

    $ignoreList = ".idea .vscode node_modules .git .gitignore *.log Thumbs.db .DS_Store"

    if ($content -match "global-ignores\s*=") {
        # แก้ไขบรรทัดที่มีอยู่
        $content = $content -replace "global-ignores\s*=.*", "global-ignores = $ignoreList"
        Write-Host "Updated existing global-ignores" -ForegroundColor Yellow
    } else {
        # เพิ่มใหม่
        if ($content -match "\[miscellany\]") {
            $content = $content -replace "\[miscellany\]", "[miscellany]`nglobal-ignores = $ignoreList"
            Write-Host "Added global-ignores to [miscellany]" -ForegroundColor Yellow
        } else {
            $content += "`n[miscellany]`nglobal-ignores = $ignoreList`n"
            Write-Host "Created [miscellany] section with global-ignores" -ForegroundColor Yellow
        }
    }

    # Backup
    Copy-Item $configPath "$configPath.backup" -Force
    Write-Host "Backup created: $configPath.backup" -ForegroundColor Green

    # บันทึก
    $content | Out-File $configPath -Encoding UTF8 -Force

    Write-Host ""
    Write-Host "SVN config updated successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Ignored patterns:" -ForegroundColor Yellow
    Write-Host "  $ignoreList" -ForegroundColor White
} else {
    Write-Host "SVN config not found!" -ForegroundColor Red
    Write-Host "Please install TortoiseSVN or SVN client first" -ForegroundColor Yellow
}

Write-Host ""
Read-Host "Press Enter to continue"