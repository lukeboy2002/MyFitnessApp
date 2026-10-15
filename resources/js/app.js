import { Passkeys, PasskeyExistsError, UserCancelledError } from '@laravel/passkeys';
import './theme';

// Globaal beschikbaar voor de @script-blokken in Livewire-componenten en de passkey-loginknop
window.Passkeys = Passkeys;
window.PasskeyExistsError = PasskeyExistsError;
window.UserCancelledError = UserCancelledError;
