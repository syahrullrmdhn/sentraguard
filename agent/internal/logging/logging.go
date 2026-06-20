package logging

import (
	"fmt"
	"io"
	"log"
	"os"
	"path/filepath"
	"strings"
)

// Logger is a minimal leveled logger writing to stdout and a rotating file.
type Logger struct {
	level int
	std   *log.Logger
}

const (
	levelDebug = iota
	levelInfo
	levelWarn
	levelError
)

func parseLevel(s string) int {
	switch strings.ToLower(s) {
	case "debug":
		return levelDebug
	case "warn", "warning":
		return levelWarn
	case "error":
		return levelError
	default:
		return levelInfo
	}
}

// New creates a logger at the given level, writing to both stdout and
// <logDir>/agent.log (created if needed). If the file can't be opened, it
// falls back to stdout only.
func New(level, logDir string) *Logger {
	var writers []io.Writer
	writers = append(writers, os.Stdout)

	if logDir != "" {
		if err := os.MkdirAll(logDir, 0o755); err == nil {
			f, err := os.OpenFile(filepath.Join(logDir, "agent.log"),
				os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0o644)
			if err == nil {
				writers = append(writers, f)
			}
		}
	}

	return &Logger{
		level: parseLevel(level),
		std:   log.New(io.MultiWriter(writers...), "", log.LstdFlags|log.LUTC),
	}
}

func (l *Logger) logf(lvl int, tag, format string, args ...any) {
	if lvl < l.level {
		return
	}
	l.std.Printf("[%s] %s", tag, fmt.Sprintf(format, args...))
}

func (l *Logger) Debug(format string, args ...any) { l.logf(levelDebug, "DEBUG", format, args...) }
func (l *Logger) Info(format string, args ...any)  { l.logf(levelInfo, "INFO", format, args...) }
func (l *Logger) Warn(format string, args ...any)  { l.logf(levelWarn, "WARN", format, args...) }
func (l *Logger) Error(format string, args ...any) { l.logf(levelError, "ERROR", format, args...) }
