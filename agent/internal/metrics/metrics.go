package metrics

import (
	"time"

	"github.com/shirou/gopsutil/v3/cpu"
	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/mem"

	"github.com/syahrullrmdhn/sentraguard/agent/internal/api"
)

// Collect samples CPU, RAM, and disk usage and returns a metrics payload ready
// for submission to the dashboard.
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

	return out, nil
}

func round2(v float64) float64 {
	return float64(int(v*100+0.5)) / 100
}
