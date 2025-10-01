param(
    [Parameter(Mandatory=$true)]
    [string]$BranchName,

    [Parameter(Mandatory=$false)]
    [string]$TargetBranch = "main",

    [Parameter(Mandatory=$false)]
    [switch]$DeleteBranch,

    [Parameter(Mandatory=$false)]
    [switch]$NoFF
)

$FolderName = $BranchName -replace '[/\\]', '-'
$FolderPath = "./$FolderName"

Write-Host "Merging branch '$BranchName' into '$TargetBranch'" -ForegroundColor Green

try {
    Write-Host "Switching to $TargetBranch..." -ForegroundColor Yellow
    git checkout $TargetBranch

    if ($LASTEXITCODE -ne 0) {
        Write-Host "Error: Failed to checkout $TargetBranch" -ForegroundColor Red
        exit 1
    }

    Write-Host "Pulling latest changes..." -ForegroundColor Yellow
    git pull origin $TargetBranch

    Write-Host "Merging $BranchName..." -ForegroundColor Yellow
    if ($NoFF) {
        git merge --no-ff $BranchName -m "Merge $BranchName into $TargetBranch"
    } else {
        git merge $BranchName
    }

    if ($LASTEXITCODE -eq 0) {
        Write-Host "[SUCCESS] Merge completed successfully!" -ForegroundColor Green

        Write-Host "Pushing changes..." -ForegroundColor Yellow
        git push origin $TargetBranch

        if ($LASTEXITCODE -eq 0) {
            Write-Host "[SUCCESS] Changes pushed successfully!" -ForegroundColor Green

            $removeWorktree = Read-Host "`nRemove worktree folder '$FolderPath'? (y/n)"
            if ($removeWorktree -eq 'y' -or $removeWorktree -eq 'Y') {
                if (Test-Path $FolderPath) {
                    git worktree remove $FolderPath --force
                    Write-Host "[SUCCESS] Worktree removed!" -ForegroundColor Green
                }
            }

            if ($DeleteBranch) {
                $deleteBranch = Read-Host "`nDelete branch '$BranchName'? (y/n)"
                if ($deleteBranch -eq 'y' -or $deleteBranch -eq 'Y') {
                    git branch -d $BranchName
                    git push origin --delete $BranchName
                    Write-Host "[SUCCESS] Branch deleted!" -ForegroundColor Green
                }
            }

        } else {
            Write-Host "[FAILED] Failed to push changes!" -ForegroundColor Red
        }
    } else {
        Write-Host "[FAILED] Merge failed!" -ForegroundColor Red
        Write-Host "Please resolve conflicts manually" -ForegroundColor Yellow
        exit 1
    }
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}