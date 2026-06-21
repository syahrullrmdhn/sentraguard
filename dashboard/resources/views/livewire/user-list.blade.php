<div>
    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="mb-4 border-2 border-ok bg-ok/10 px-4 py-3 text-sm font-medium text-ink">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 border-2 border-danger bg-danger/10 px-4 py-3 text-sm font-medium text-ink">{{ session('error') }}</div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari user..."
                   class="w-full max-w-xs neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
            <select wire:model.live="roleFilter"
                    class="neu-inset px-3 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
                <option value="">Semua role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                @endforeach
            </select>
        </div>
        <button wire:click="openCreate"
                class="inline-flex items-center gap-2 bg-accent px-4 py-2.5 text-sm font-bold uppercase tracking-wide text-white brutal brutal-hover brutal-press">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah User
        </button>
    </div>

    {{-- Table --}}
    <div class="mt-5 bg-white brutal-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-ink text-left swiss-label">
                        <th class="px-6 py-3.5">Nama</th>
                        <th class="px-6 py-3.5">Email</th>
                        <th class="px-6 py-3.5">Role</th>
                        <th class="px-6 py-3.5">Dibuat</th>
                        <th class="px-6 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/8">
                    @forelse ($users as $user)
                        <tr class="hover:bg-paper-soft">
                            <td class="px-6 py-3.5 font-bold text-ink">{{ $user->name }}</td>
                            <td class="px-6 py-3.5 text-ink-soft" style="font-family: var(--font-mono);">{{ $user->email }}</td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex border-2 border-ink bg-paper px-2.5 py-0.5 text-xs font-bold uppercase text-ink">
                                    {{ $user->role->display_name }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-ink-soft/60">{{ $user->created_at->format('d M Y') }}</td>
                            <td class="px-6 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($user->id !== auth()->id())
                                        <button wire:click="openEdit({{ $user->id }})"
                                                class="bg-white px-3 py-1.5 text-xs font-bold uppercase text-ink brutal brutal-hover brutal-press">
                                            Edit
                                        </button>
                                        <button wire:click="delete({{ $user->id }})"
                                                wire:confirm="Yakin hapus user {{ $user->name }}?"
                                                class="bg-danger px-3 py-1.5 text-xs font-bold uppercase text-white brutal brutal-hover brutal-press">
                                            Hapus
                                        </button>
                                    @else
                                        <span class="text-xs text-ink-soft/50">(Anda)</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-ink-soft/50">Belum ada user.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t-2 border-ink p-4">
            {{ $users->links() }}
        </div>
    </div>

    {{-- Modal Create/Edit --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 animate-fade-in" 
             style="background: rgba(17,17,17,0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);" 
             wire:click.self="$set('showModal', false)">
            <div class="w-full max-w-md bg-white brutal-lg p-6 animate-slide-up" wire:click.stop>
                <div class="flex items-center justify-between border-b-2 border-ink pb-3">
                    <h3 class="text-lg font-bold uppercase tracking-wide text-ink">{{ $editMode ? 'Edit User' : 'Tambah User' }}</h3>
                    <button wire:click="$set('showModal', false)" class="text-ink-soft hover:text-ink">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="save" class="mt-4 space-y-4">
                    {{-- Name --}}
                    <div>
                        <label class="swiss-label">Nama</label>
                        <input type="text" wire:model="name"
                               class="mt-1 w-full neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40"
                               placeholder="John Doe">
                        @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="swiss-label">Email</label>
                        <input type="email" wire:model="email"
                               class="mt-1 w-full neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40"
                               placeholder="john@example.com">
                        @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Role --}}
                    <div>
                        <label class="swiss-label">Role</label>
                        <select wire:model="role_id"
                                class="mt-1 w-full neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40">
                            <option value="">-- Pilih Role --</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                        @error('role_id') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label class="swiss-label">Password {{ $editMode ? '(kosongkan jika tidak diubah)' : '' }}</label>
                        <input type="password" wire:model="password"
                               class="mt-1 w-full neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40"
                               placeholder="••••••••">
                        @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password Confirmation --}}
                    <div>
                        <label class="swiss-label">Konfirmasi Password</label>
                        <input type="password" wire:model="password_confirmation"
                               class="mt-1 w-full neu-inset px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-accent/40"
                               placeholder="••••••••">
                    </div>

                    {{-- Actions --}}
                    <div class="flex gap-3 border-t-2 border-ink pt-4">
                        <button type="button" wire:click="$set('showModal', false)"
                                class="flex-1 bg-white px-4 py-2.5 text-sm font-bold uppercase text-ink brutal brutal-hover brutal-press">
                            Batal
                        </button>
                        <button type="submit"
                                class="flex-1 bg-accent px-4 py-2.5 text-sm font-bold uppercase text-white brutal brutal-hover brutal-press">
                            {{ $editMode ? 'Perbarui' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
