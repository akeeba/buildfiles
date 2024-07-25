## @package   buildfiles
## @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
## @license   GNU General Public License version 3, or later

Param(
	[string]$operation,
	[string]$sitepath
)

function showUsage()
{
	Write-Host "Usage: all <command>" -Foreground DarkYellow
	Write-Host ""
	Write-Host All repositories -Foreground Blue
	Write-Host "pull     Pull from Git"
	Write-Host "push     Push to Git"
	Write-Host "tidy     Perform local Git repo housekeeping"
	Write-Host "status   Report repositories with uncommitted changes"
	Write-Host "branch   Which Git branch am I in?"
	Write-Host "fixcrlf  Fix CRLF under Windows"
	Write-Host Using Akeeba Build Files -Foreground Blue
	Write-Host "build    Run the Phing 'git' task to rebuild the software"
	Write-Host "link     Internal relink"
	Write-Host "relink   Relink to a site, e.g. all relink c:\sites\mysite"
}

if (!$operation)
{
	showUsage
	exit 255
}

Write-Host "All - Loop all repositories" -Foreground White
Write-Host ""

Get-ChildItem -Directory | ForEach-Object {
	$d = $_.Name
	Push-Location $d

	$hasDotGit = Test-Path .git
	
	if ($hasDotGit -eq $False)
	{
		Pop-Location
		
		return
	}
	
	$thisRepoMetrics = git remote -v | Select-String -Pattern "git@github.com" | Measure-Object -Line
	
	if ($thisRepoMetrics.Lines -lt 1)
	{
		Pop-Location
		
		return
	}
	
	Switch ($operation)
	{
		"pull" {
			Write-Host "Pulling " -Foreground Blue -NoNewline
			Write-Host $d -Foreground Cyan
			git pull --all
		}

		"push" {
			Write-Host "Pushing " -Foreground Green -NoNewline
			Write-Host $d -Foreground Cyan
			git push --all
		}
		
		"tidy" {
			Write-Host "Housekeeping " -Foreground Red -NoNewline
			Write-Host $d -Foreground Cyan
			git remote prune origin
			git gc
		}

		"status" {
			if ( (git status --porcelain | Measure-Object -Line).Lines -gt 0)
			{
				Write-Host "Dirty " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan				
			}
		}
		
		"link" {
			if (Test-Path build.xml)
			{
				Write-Host "Linking " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan								

				phing link
			}

			if (Test-Path build)
			{
				Write-Host "Linking " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan

				cd build
				phing link
			}
		}
		
		"build" {
			if (Test-Path build.xml)
			{
				Write-Host "Building " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan

				phing git
			}

			if (Test-Path build)
			{
				Write-Host "Building " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan

				cd build
				phing git
			}
		}

		"relink" {
			if (Test-Path build.xml)
			{
				Write-Host "Relinking " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan

				phing relink -Dsite="${sitepath}"
			}

			if (Test-Path build)
			{
				Write-Host "Relinking " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan

				cd build
				phing relink -Dsite="${sitepath}"
			}
		}

		"branch" {
			$currentBranch = git rev-parse --abbrev-ref HEAD
			$color = "Red"
			
			if ($currentBranch -eq "development") {
				$color = "Green"
			} elseif ($currentBranch -eq "master") {
				$color = "Yellow"
			} elseif ($currentBranch -eq "main") {
				$color = "Yellow"
			} elseif ($currentBranch -eq "kyrion") {
				$color = "Yellow"
			}
			
			Write-Host "Branch " -Foreground Magenta -NoNewline
			"{0, -25}" -f $d | Write-Host -Foreground Cyan -NoNewline
			Write-Host `t$currentBranch -Foreground $color
		}
		
		"fixcrlf" {
			Write-Host "Fixing CRLF " -Foreground DarkGreen -NoNewline
			Write-Host $d -Foreground Cyan

			git config --unset core.fileMode
			git config --unset core.filemode
			git config --unset core.autocrlf
		}
		
		"default" {
			Write-Host "Unknown command $operation" -Foreground Magenta
			
			Pop-Location
			
			Exit 1
		}
	}
	
	Pop-Location
}
