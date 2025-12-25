<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FileBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class FileBrowserTest extends TestCase
{
    use RefreshDatabase;

    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = storage_path('app/test-file-browser');
        File::makeDirectory($this->testDir, 0755, true, true);
        File::put($this->testDir.'/test.txt', 'Hello World');
        File::makeDirectory($this->testDir.'/subdir', 0755, true, true);
        File::put($this->testDir.'/subdir/nested.php', '<?php echo "test";');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testDir);
        parent::tearDown();
    }

    public function test_it_renders_with_base_path(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->assertStatus(200);
    }

    public function test_it_lists_files_in_directory(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->assertSee('test.txt')
            ->assertSee('subdir');
    }

    public function test_it_can_expand_directory(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->call('toggleDirectory', 'subdir')
            ->assertSee('nested.php');
    }

    public function test_it_can_collapse_directory(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->call('toggleDirectory', 'subdir')
            ->call('toggleDirectory', 'subdir')
            ->assertDontSee('nested.php');
    }

    public function test_it_can_preview_file(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->call('selectFile', 'test.txt')
            ->assertSet('selectedFile', 'test.txt')
            ->assertSee('Hello World');
    }

    public function test_it_prevents_navigation_above_base_path(): void
    {
        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->call('selectFile', '../../../etc/passwd')
            ->assertSet('selectedFile', null);
    }

    public function test_it_ignores_common_directories(): void
    {
        File::makeDirectory($this->testDir.'/node_modules', 0755, true, true);
        File::put($this->testDir.'/node_modules/package.json', '{}');
        File::makeDirectory($this->testDir.'/.git', 0755, true, true);

        Livewire::test(FileBrowser::class, ['basePath' => $this->testDir])
            ->assertDontSee('node_modules')
            ->assertDontSee('.git');
    }
}
