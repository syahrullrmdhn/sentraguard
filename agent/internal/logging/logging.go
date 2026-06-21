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

// safeMultiWriter writes to all underlying writers and, unlike io.MultiWriter,
// does NOT abort when one writer errors. This is critical for Windows Services,
// where os.Stdout is an invalid handle: a stdout write error must never block
// writes to the log file.
type safeMultiWriter struct {
	writers []io.Writer
}

func (s *safeMultiWriter) Write(p []byte) (int, error) {
	for _, w := range s.writers {
		_, _ = w.Write(p)
	}
	return len(p), nil
}

// New creates a logger at the given level, writing to both a log file and
// stdout. The log FILE is written first and independently of stdout, so a
// broken stdout (e.g. running as a Windows Service with no console) can never
// prevent file logging.
func New(level, logDir string) *Logger {
	var writers []io.Writer

	// File first, so it always gets written regardless of stdout state.
	if logDir != "" {
		if err := os.MkdirAll(logDir, 0o755); err == nil {
			f, err := os.OpenFile(filepath.Join(logDir, "agent.log"),
				os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0o644)
			if err == nil {
				writers = append(writers, f)
			}
		}
	}

	writers = append(writers, os.Stdout)

	return &Logger{
		level: parseLevel(level),
		std:   log.New(&safeMultiWriter{writers: writers}, "", log.LstdFlags|log.LUTC),
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
