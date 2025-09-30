param(
    [Parameter(Mandatory=$true)]
    [string]$BranchName,

    [Parameter(Mandatory=$false)]
    [string]$BaseBranch = "main"
)

$FolderName = $BranchName -replace '[/\\]', '-'
$FolderPath = "./$FolderName"

Write-Host "Creating worktree for branch: $BranchName" -ForegroundColor Green
Write-Host "Folder name: $FolderName" -ForegroundColor Yellow
Write-Host "Base branch: $BaseBranch" -ForegroundColor Cyan

try {
    if (Test-Path $FolderPath) {
        Write-Host "Error: Folder '$FolderPath' already exists!" -ForegroundColor Red
        exit 1
    }

    $gitStatus = git status 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Error: Not in a git repository!" -ForegroundColor Red
        exit 1
    }

    Write-Host "Creating worktree..." -ForegroundColor Yellow
    git worktree add -b $BranchName $FolderPath $BaseBranch

    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Worktree created successfully!" -ForegroundColor Green
        Write-Host "📁 Location: $FolderPath" -ForegroundColor White
        Write-Host "🌿 Branch: $BranchName" -ForegroundColor White

        # แสดงรายการ worktree ทั้งหมด
        Write-Host "`nCurrent worktrees:" -ForegroundColor Cyan
        git worktree list

        $openInPhpStorm = Read-Host "`nOpen in PHPStorm? (y/n)"
        if ($openInPhpStorm -eq 'y' -or $openInPhpStorm -eq 'Y') {
            # ลองหา PHPStorm executable
            $phpStormPaths = @(
                "${env:ProgramFiles}\JetBrains\PhpStorm*\bin\phpstorm64.exe",
                "${env:LOCALAPPDATA}\JetBrains\PhpStorm*\bin\phpstorm64.exe",
                "${env:ProgramFiles(x86)}\JetBrains\PhpStorm*\bin\phpstorm64.exe"
            )

            $phpStormExe = $null
            foreach ($path in $phpStormPaths) {
                $found = Get-ChildItem $path -ErrorAction SilentlyContinue | Select-Object -First 1
                if ($found) {
                    $phpStormExe = $found.FullName
                    break
                }
            }

            if ($phpStormExe) {
                Write-Host "Opening in PHPStorm..." -ForegroundColor Yellow
                Start-Process $phpStormExe -ArgumentList (Resolve-Path $FolderPath).Path
            } else {
                Write-Host "PHPStorm executable not found. Please open manually: $FolderPath" -ForegroundColor Yellow
            }
        }
    } else {
        Write-Host "❌ Failed to create worktree!" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}