<?php

namespace App\Modules\Notebook;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Contracts\Overlays\ProvidesAdminOverlaysInterface;
use App\Admin\Contracts\Trash\ProvidesTrashInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Admin\Overlays\AdminOverlay;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Contracts\Modules\UninstallableInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Accounts\Contracts\Events\AccountEntryPosted;
use App\Modules\Notebook\Areas\Admin\Trash\NoteTrash;
use App\Modules\Notebook\Authorization\NotebookPermission;
use App\Modules\Notebook\Contracts\NoteReaderInterface;
use App\Modules\Notebook\Domain\Listeners\FollowLedgerEntries;
use App\Modules\Notebook\Domain\Services\RuleNoteReader;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * The counter's notebook — «الكشكول» — for the rush: when the shop is full
 * and there is no time to open an account, whoever is at the counter jots
 * «أم كريم زيت وسكر 75», or says it into the microphone, from a button on
 * every screen. When it is quiet, the notebook is gone through: each note is
 * heard or read, the account it belongs to is suggested, and the drawer that
 * records entries opens on it, filled in, for a person to check and save.
 *
 * A note is not money. It moves no balance, and posts nothing by itself:
 * the entry is made through Accounts' own drawer and ledger, and the note
 * learns it was posted — or undone — from the ledger's announcements.
 */
final class NotebookServiceProvider extends ModuleServiceProvider implements DependsOnModulesInterface, ProvidesAdminNavigationInterface, ProvidesAdminOverlaysInterface, ProvidesPermissionsInterface, ProvidesTrashInterface, UninstallableInterface
{
    /** Its defaults, as config('notebook.…'). */
    protected ?string $config = 'Config/notebook.php';

    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    /**
     * What other modules may ask the container for. Each implementation is
     * `#[Scoped]`: one instance per request.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        NoteReaderInterface::class => RuleNoteReader::class,
    ];

    public function key(): string
    {
        return 'notebook';
    }

    /**
     * A note becomes an entry on a customer's account: Accounts records it,
     * and Customers is who it is about.
     *
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return ['accounts', 'customers'];
    }

    /**
     * @return list<NotebookPermission>
     */
    public function permissions(): array
    {
        return NotebookPermission::cases();
    }

    public function adminNavigation(): NavigationSection
    {
        // Under the counter's heading, beside the customers and the day's
        // journal it is posted to; the sidebar merges them into one section.
        return new NavigationSection('shell.navigation.group.counter', [
            new NavigationItem(
                label: 'notebook::module.navigation.notebook',
                route: 'admin.notebook.index',
                icon: 'journal-bookmark',
                permission: NotebookPermission::Review,
            ),
        ]);
    }

    /**
     * The quick note, a button on every screen for whoever may jot.
     *
     * @return list<AdminOverlay>
     */
    public function adminOverlays(): array
    {
        return [new AdminOverlay('notebook::admin.capture', NotebookPermission::Write)];
    }

    /**
     * Notes deleted from the notebook, kept with their clips to be brought back.
     *
     * @return list<class-string<NoteTrash>>
     */
    public function adminTrashSources(): array
    {
        return [NoteTrash::class];
    }

    public function boot(): void
    {
        // Accounts announces every entry; a note follows its own.
        Event::listen(AccountEntryPosted::class, FollowLedgerEntries::class);
    }

    /**
     * The voice clips on the private disk, which no migration knows about.
     */
    public function leftovers(): array
    {
        $clips = count($this->disk()->allFiles('notebook'));

        return $clips === 0 ? [] : [sprintf('%d voice %s on the %s disk', $clips, $clips === 1 ? 'note' : 'notes', config('notebook.voice.disk', 'local'))];
    }

    public function uninstall(): void
    {
        $this->disk()->deleteDirectory('notebook');
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('notebook.voice.disk', 'local'));
    }
}
