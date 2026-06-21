package metrics

import (
	"time"

	"github.com/shirou/gopsutil/v3/cpu"
	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/mem"
	"github.com/shirou/gopsutil/v3/net"

	"github.com/syahrullrmdhn/sentraguard/agent/internal/api"
)

var (
	lastNetStats  []net.IOCountersStat
	lastNetTime   time.Time
	networkInited bool
)

// Collect samples CPU, RAM, disk usage, and network bandwidth.
// Returns a metrics payload ready for submission to the dashboard.
func Collect() (api.MetricsRequest, error) {
	var out api.MetricsRequest

	cpuPct, err := cpu.Percent(time.Second, false)
	if err == nil && len(cpuPct) > 0 {
		out.CPUPercent = round2(cpuPct[0])
	}

	if vm, err := mem.VirtualMemory(); err == nil {
		out.RAMUsedMB = vm.Used / 1024 / 1024
		out.RAMTotalMB = vm.Total / 1024 / 1024
	}

	if du, err := disk.Usage(systemDrive); err == nil {
		out.DiskUsedGB = round2(float64(du.Used) / 1024 / 1024 / 1024)
		out.DiskTotalGB = round2(float64(du.Total) / 1024 / 1024 / 1024)
	}

	// Network bandwidth (Mbps) — calculate delta since last call
	if netStats, err := net.IOCounters(false); err == nil && len(netStats) > 0 {
		now := time.Now()
		if networkInited && !lastNetTime.IsZero() {
			elapsed := now.Sub(lastNetTime).Seconds()
			if elapsed > 0 {
				// Sum all interfaces
				var totalSent, totalRecv uint64
				for i, stat := range netStats {
					if i < len(lastNetStats) {
						totalSent += stat.BytesSent - lastNetStats[i].BytesSent
						totalRecv += stat.BytesRecv - lastNetStats[i].BytesRecv
					}
				}
				// Convert bytes to megabits per second
				out.NetworkSentMbps = round2((float64(totalSent) * 8) / elapsed / 1_000_000)
				out.NetworkRecvMbps = round2((float64(totalRecv) * 8) / elapsed / 1_000_000)
			}
		}
		lastNetStats = netStats
		lastNetTime = now
		networkInited = true
	}

	return out, nil
}

func round2(v float64) float64 {
	return float64(int(v*100+0.5)) / 100
}
