package main

import (
	"fmt"
	"io"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"

	"fyne.io/fyne/v2/app"
	"fyne.io/fyne/v2/container"
	"fyne.io/fyne/v2/dialog"
	"fyne.io/fyne/v2/widget"
)

const (
	defaultServerURL = "https://sentraguard.mastolongin.web.id"
	appTitle         = "SentraGuard Agent Installer"
)

func main() {
	// Check admin rights
	if !isAdmin() {
		dialog.ShowError(fmt.Errorf("Administrator privileges required.\n\nPlease right-click and select 'Run as administrator'."), nil)
		os.Exit(1)
	}

	myApp := app.New()
	myWindow := myApp.NewWindow(appTitle)
	myWindow.Resize(fyne.NewSize(500, 400))
	myWindow.SetFixedSize(true)

	// Create UI elements
	titleLabel := widget.NewLabelWithStyle(appTitle, fyne.TextAlignCenter, fyne.TextStyle{Bold: true})
	descLabel := widget.NewLabel("Install SentraGuard monitoring agent on this Windows server.")
	
	serverEntry := widget.NewEntry()
	serverEntry.SetText(defaultServerURL)
	serverEntry.SetPlaceHolder("https://your-server.com")
	
	tokenEntry := widget.NewEntry()
	tokenEntry.SetPlaceHolder("AGT_xxx...")
	
	statusLabel := widget.NewLabel("")
	progressBar := widget.NewProgressBarInfinite()
	progressBar.Hide()
	
	installBtn := widget.NewButton("Install", nil)
	cancelBtn := widget.NewButton("Cancel", func() {
		myWindow.Close()
	})

	// Install button handler
	installBtn.OnTapped = func() {
		server := strings.TrimSpace(serverEntry.Text)
		token := strings.TrimSpace(tokenEntry.Text)

		// Validate
		if server == "" {
			dialog.ShowError(fmt.Errorf("Server URL is required"), myWindow)
			return
		}
		if token == "" {
			dialog.ShowError(fmt.Errorf("Registration token is required"), myWindow)
			return
		}
		if !strings.HasPrefix(token, "AGT_") {
			dialog.ShowError(fmt.Errorf("Invalid token format. Token should start with AGT_"), myWindow)
			return
		}

		// Disable controls
		installBtn.Disable()
		serverEntry.Disable()
		tokenEntry.Disable()
		progressBar.Show()

		// Run installation
		go func() {
			err := runInstallation(server, token, statusLabel)
			
			// Re-enable controls
			installBtn.Enable()
			serverEntry.Enable()
			tokenEntry.Enable()
			progressBar.Hide()

			if err != nil {
				statusLabel.SetText(fmt.Sprintf("✗ Installation failed: %v", err))
				dialog.ShowError(fmt.Errorf("Installation failed:\n\n%v\n\nPlease check the server URL and token, then try again.", err), myWindow)
			} else {
				statusLabel.SetText("✓ Installation completed successfully!")
				dialog.ShowInformation("Installation Complete", 
					"SentraGuard agent installed successfully!\n\nThe agent will start monitoring this server shortly.", 
					myWindow)
				time.Sleep(2 * time.Second)
				myWindow.Close()
			}
		}()
	}

	// Layout
	content := container.NewVBox(
		titleLabel,
		descLabel,
		widget.NewLabel(""),
		widget.NewLabel("Server URL:"),
		serverEntry,
		widget.NewLabel(""),
		widget.NewLabel("Registration Token (from SentraGuard console):"),
		tokenEntry,
		widget.NewLabel(""),
		statusLabel,
		progressBar,
		widget.NewLabel(""),
		container.NewHBox(
			widget.NewLabel(""),
			installBtn,
			cancelBtn,
		),
	)

	myWindow.SetContent(content)
	myWindow.ShowAndRun()
}

func runInstallation(server, token string, statusLabel *widget.Label) error {
	// Step 1: Download agent
	statusLabel.SetText("Downloading agent...")
	
	agentURL := server + "/download/agent"
	tempDir := os.TempDir()
	agentPath := filepath.Join(tempDir, "sentraguard-agent.exe")

	if err := downloadFile(agentPath, agentURL); err != nil {
		return fmt.Errorf("failed to download agent: %w", err)
	}
	defer os.Remove(agentPath)

	// Step 2: Install agent
	statusLabel.SetText("Installing agent service...")
	
	cmd := exec.Command(agentPath, "install", "--server", server, "--token", token)
	output, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("installation failed: %s - %w", string(output), err)
	}

	// Step 3: Verify installation
	statusLabel.SetText("Verifying installation...")
	time.Sleep(2 * time.Second)

	cmd = exec.Command("sc", "query", "SentraGuardAgent")
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("service 'SentraGuardAgent' not found after installation")
	}

	return nil
}

func downloadFile(filepath string, url string) error {
	resp, err := http.Get(url)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("server returned status %d", resp.StatusCode)
	}

	out, err := os.Create(filepath)
	if err != nil {
		return err
	}
	defer out.Close()

	_, err = io.Copy(out, resp.Body)
	return err
}

func isAdmin() bool {
	cmd := exec.Command("net", "session")
	err := cmd.Run()
	return err == nil
}
