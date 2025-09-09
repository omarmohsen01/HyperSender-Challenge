<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeEnum extends Command
{
    protected $signature = 'make:enum {name}';
    protected $description = 'Create a new Enum class in the Enums namespace';

    public function handle()
    {
        $name = $this->argument('name');
        $directory = app_path('Enums');
        $filePath = "$directory/{$name}.php";

        // Ensure the Enums directory exists
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0777, true);
        }

        // Check if file already exists
        if (File::exists($filePath)) {
            $this->error("Enum {$name} already exists!");
            return Command::FAILURE;
        }

        // Enum template
        $content = "<?php

namespace App\Enums;

enum {$name}: string {
    case EXAMPLE = 'example';
    public function name(): string
    {
        return match (\$this) {
            self::EXAMPLE => 'Example',
        };
    }
    public static function all(): array
    {
        return array_map(fn (\$enum) => [
            'value' => \$enum->value,
            'name' => \$enum->name(),
        ], self::cases());
    }
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
            ";

        // Create the file
        File::put($filePath, $content);
        $this->info("Enum {$name} created successfully at {$filePath}");

        return Command::SUCCESS;
    }
}
