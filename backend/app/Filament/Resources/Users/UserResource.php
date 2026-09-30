<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

// Users register through the API; admins edit profile, role and status. No passwords here; no deletes (deactivate instead).
class UserResource extends Resource
{
    public const STATUSES = ['active' => 'active', 'suspended' => 'suspended'];

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('phone')->maxLength(20)->regex('/^\+[1-9]\d{6,14}$/')->unique(ignoreRecord: true),
            Select::make('role_id')->label('Role')->relationship('role', 'name')->required(),
            Select::make('country_id')->label('Country')->relationship('country', 'name'),
            Select::make('locale')->options(fn () => array_combine(config('app.supported_locales'), config('app.supported_locales')))->required(),
            Select::make('status')->options(self::STATUSES)->required()
                ->helperText('Suspending also signs the user out of the apps (API tokens revoked).'),
        ]);
    }

    /**
     * Saves an admin edit. Role and status changes are audited (D6); a non-active user loses every API token,
     * so a suspended account can't keep using a token issued earlier (open item from Phase 1).
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveEdit(User $user, array $data): User
    {
        $before = ['role' => $user->role?->name, 'status' => $user->status];

        $user->forceFill($data)->save(); // status isn't mass-assignable on purpose
        $user->load('role');
        $after = ['role' => $user->role?->name, 'status' => $user->status];

        if ($before !== $after) {
            AuditLog::record('user.update', $user, ['from' => $before, 'to' => $after]);
        }
        if (! $user->isActive()) {
            $user->tokens()->delete();
        }

        return $user;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('role.name')->badge(),
                TextColumn::make('country.name'),
                TextColumn::make('locale'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->relationship('role', 'name'),
                SelectFilter::make('status')->options(self::STATUSES),
            ])
            ->recordActions([
                EditAction::make()->using(fn (User $record, array $data) => self::saveEdit($record, $data)),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
