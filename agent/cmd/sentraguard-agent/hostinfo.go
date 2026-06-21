package main

import (
	"encoding/json"
	"io"
	"net"
	"net/http"
	"os"
	"runtime"
	"time"
)

// privateIP returns the first non-loopback IPv4 address, or "" if none.
func privateIP() string {
	addrs, err := net.InterfaceAddrs()
	if err != nil {
		return ""
	}
	for _, addr := range addrs {
		if ipnet, ok := addr.(*net.IPNet); ok && !ipnet.IP.IsLoopback() {
			if v4 := ipnet.IP.To4(); v4 != nil {
				return v4.String()
			}
		}
	}
	return ""
}

// publicIP queries ipinfo.io to detect the public IP address. Returns "" on
// failure (timeout, network error, invalid response). This is necessary for
// cloud VMs (AWS, Azure, GCP) where the local network interface only sees the
// private IP — the public IP is NAT'd at the cloud provider's edge.
func publicIP() string {
	const token = "95e609eb56a3ac"
	client := &http.Client{Timeout: 5 * time.Second}
	
	req, err := http.NewRequest("GET", "https://ipinfo.io/json?token="+token, nil)
	if err != nil {
		return ""
	}
	
	resp, err := client.Do(req)
	if err != nil {
		return ""
	}
	defer resp.Body.Close()
	
	if resp.StatusCode != 200 {
		return ""
	}
	
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return ""
	}
	
	var result struct {
		IP string `json:"ip"`
	}
	if err := json.Unmarshal(body, &result); err != nil {
		return ""
	}
	
	return result.IP
}

// osName returns a coarse OS name.
func osName() string {
	switch runtime.GOOS {
	case "windows":
		return "Windows"
	case "linux":
		return "Linux"
	case "darwin":
		return "macOS"
	default:
		return runtime.GOOS
	}
}

// machineID returns a stable-ish machine identifier. On Windows the installer
// can override this; here we fall back to hostname which is adequate for MVP.
func machineID() string {
	if id := os.Getenv("SENTRAGUARD_MACHINE_ID"); id != "" {
		return id
	}
	host, _ := os.Hostname()
	return host
}
