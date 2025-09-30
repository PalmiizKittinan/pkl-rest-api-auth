param(
    [Parameter(Mandatory=$true)]
    [string]$FolderName
)

$FolderPath = "./$FolderName"

Write-Host "Removing worktree: $FolderName" -ForegroundColor Yellow

try {
    # ตรวจสอบว่า folder มีอยู่หรือไม่
    if (-not (Test-Path $FolderPath)) {
        Write-Host "Error: Folder '$FolderPath' does not exist!" -ForegroundColor Red
        exit 1
    }

    # ลบ worktree
    git worktree remove $FolderPath --force

    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Worktree removed successfully!" -ForegroundColor Green

        # ทำความสะอาด
        git worktree prune

        Write-Host "`nRemaining worktrees:" -ForegroundColor Cyan
        git worktree list
    } else {
        Write-Host "❌ Failed to remove worktree!" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}