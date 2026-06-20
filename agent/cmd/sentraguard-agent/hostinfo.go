package main

import (
	"net"
	"os"
	"runtime"
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
