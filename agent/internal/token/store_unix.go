//go:build !windows

package token

import (
	"encoding/base64"
	"fmt"
	"os"
	"path/filepath"
)

// fileStore is a development-only token store for non-Windows platforms. It
// base64-encodes the token to a 0600 file under the agent data dir. This is NOT
// secure storage — production runs on Windows with DPAPI.
type fileStore struct {
	path string
}

// NewStore returns the dev file-backed token store.
func NewStore() Store {
	dir := os.Getenv("SENTRAGUARD_DATA_DIR")
	if dir == "" {
		dir = filepath.Join(os.TempDir(), "sentraguard-agent")
	}
	return &fileStore{path: filepath.Join(dir, ".runtime_token")}
}

func (s *fileStore) Save(token string) error {
	if err := os.MkdirAll(filepath.Dir(s.path), 0o755); err != nil {
		return err
	}
	enc := base64.StdEncoding.EncodeToString([]byte(token))
	return os.WriteFile(s.path, []byte(enc), 0o600)
}

func (s *fileStore) Load() (string, error) {
	data, err := os.ReadFile(s.path)
	if err != nil {
		return "", fmt.Errorf("read token file: %w", err)
	}
	dec, err := base64.StdEncoding.DecodeString(string(data))
	if err != nil {
		return "", fmt.Errorf("decode token: %w", err)
	}
	return string(dec), nil
}

func (s *fileStore) Delete() error {
	err := os.Remove(s.path)
	if os.IsNotExist(err) {
		return nil
	}
	return err
}
