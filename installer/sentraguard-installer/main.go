package main

import (
	"embed"
	"fmt"
	"html/template"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

//go:embed templates/*
var content embed.FS

const (
	defaultServerURL = "https://sentraguard.mastolongin.web.id"
	port             = "8765"
)

type PageData struct {
	ServerURL string
	Token     string
	Status    string
	Error     string
	Success   bool
}

func main() {
	// Check admin rights on Windows
	if runtime.GOOS == "windows" && !isAdmin() {
		fmt.Println("ERROR: Administrator privileges required!")
		fmt.Println("Please right-click this file and select 'Run as administrator'")
		fmt.Println("\nPress Enter to exit...")
		fmt.Scanln()
		os.Exit(1)
	}

	http.HandleFunc("/", handleIndex)
	http.HandleFunc("/install", handleInstall)
	http.HandleFunc("/shutdown", handleShutdown)

	url := fmt.Sprintf("http://localhost:%s", port)
	fmt.Printf("SentraGuard Agent Installer\n")
	fmt.Printf("===========================\n\n")
	fmt.Printf("Opening installer at: %s\n", url)
	fmt.Printf("Press Ctrl+C to exit\n\n")

	// Open browser
	go openBrowser(url)

	// Start server
	log.Fatal(http.ListenAndServe(":"+port, nil))
}

func handleIndex(w http.ResponseWriter, r *http.Request) {
	tmpl, err := template.ParseFS(content, "templates/index.html")
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	data := PageData{
		ServerURL: defaultServerURL,
	}

	tmpl.Execute(w, data)
}

func handleInstall(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Redirect(w, r, "/", http.StatusSeeOther)
		return
	}

	server := strings.TrimSpace(r.FormValue("server"))
	token := strings.TrimSpace(r.FormValue("token"))

	// Validate
	if server == "" || token == "" {
		renderResult(w, server, token, "", "Server URL and token are required", false)
		return
	}

	if !strings.HasPrefix(token, "AGT_") {
		renderResult(w, server, token, "", "Invalid token format. Token should start with AGT_", false)
		return
	}

	// Run installation
	status, err := runInstallation(server, token)
	if err != nil {
		renderResult(w, server, token, status, err.Error(), false)
		return
	}

	renderResult(w, server, token, status, "", true)
}

func handleShutdown(w http.ResponseWriter, r *http.Request) {
	fmt.Fprintln(w, "Installer closed. You can close this window.")
	go func() {
		time.Sleep(1 * time.Second)
		os.Exit(0)
	}()
}

func renderResult(w http.ResponseWriter, server, token, status, errMsg string, success bool) {
	tmpl, err := template.ParseFS(content, "templates/index.html")
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	data := PageData{
		ServerURL: server,
		Token:     token,
		Status:    status,
		Error:     errMsg,
		Success:   success,
	}

	tmpl.Execute(w, data)
}

func runInstallation(server, token string) (string, error) {
	// Step 1: Download agent
	agentURL := server + "/download/agent"
	tempDir := os.TempDir()
	
	// Use random filename to avoid conflicts
	randomName := fmt.Sprintf("sentraguard-agent-%d.exe", time.Now().UnixNano())
	agentPath := filepath.Join(tempDir, randomName)

	if err := downloadFile(agentPath, agentURL); err != nil {
		return "Download failed", fmt.Errorf("failed to download agent: %w", err)
	}
	
	// Wait for antivirus scan to complete
	time.Sleep(2 * time.Second)
	
	// Cleanup on exit (best effort, ignore errors)
	defer func() {
		time.Sleep(1 * time.Second)
		os.Remove(agentPath)
	}()

	// Step 2: Install agent
	cmd := exec.Command(agentPath, "install", "--server", server, "--token", token)
	output, err := cmd.CombinedOutput()
	if err != nil {
		return "Installation failed", fmt.Errorf("installation failed: %s - %w", string(output), err)
	}

	// Step 3: Verify installation
	time.Sleep(2 * time.Second)

	cmd = exec.Command("sc", "query", "SentraGuardAgent")
	if err := cmd.Run(); err != nil {
		return "Verification failed", fmt.Errorf("service 'SentraGuardAgent' not found after installation")
	}

	return "Installation completed successfully", nil
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

func openBrowser(url string) {
	time.Sleep(500 * time.Millisecond)
	var cmd *exec.Cmd

	switch runtime.GOOS {
	case "windows":
		cmd = exec.Command("rundll32", "url.dll,FileProtocolHandler", url)
	case "darwin":
		cmd = exec.Command("open", url)
	default:
		cmd = exec.Command("xdg-open", url)
	}

	cmd.Run()
}
