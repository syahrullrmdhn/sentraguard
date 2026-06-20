package token

// Store abstracts secure runtime-token persistence. On Windows this is backed
// by DPAPI + Credential Manager; on other platforms a dev file fallback is used.
type Store interface {
	Save(token string) error
	Load() (string, error)
	Delete() error
}

const (
	credTarget   = "SentraGuardAgent"
	credUsername = "runtime_token"
)
