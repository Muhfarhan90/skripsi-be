<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    // Nama command yang akan dijalankan di terminal
    protected $signature = 'make:crud {name}';

    // Deskripsi command
    protected $description = 'Membuat CRUD stubs lengkap (service, controller, requests, resource)';

    public function handle()
    {
        $baseName = Str::studly($this->argument('name'));

        $this->generateCrudStubs($baseName);

        return Command::SUCCESS;
    }

    protected function generateCrudStubs(string $modelName): void
    {
        $servicePath = app_path("Services/{$modelName}Service.php");
        $controllerPath = app_path("Http/Controllers/Api/Admin/{$modelName}Controller.php");
        $storeRequestPath = app_path("Http/Requests/Admin/{$modelName}/Store{$modelName}Request.php");
        $updateRequestPath = app_path("Http/Requests/Admin/{$modelName}/Update{$modelName}Request.php");
        $resourcePath = app_path("Http/Resources/{$modelName}Resource.php");

        $targets = [
            $servicePath,
            $controllerPath,
            $storeRequestPath,
            $updateRequestPath,
            $resourcePath,
        ];

        $existing = array_filter($targets, fn(string $path) => File::exists($path));
        if (!empty($existing)) {
            $this->error('Gagal membuat CRUD stubs karena ada file yang sudah ada:');
            foreach ($existing as $file) {
                $this->line('- ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file));
            }
            return;
        }

        File::ensureDirectoryExists(app_path('Services'));
        File::ensureDirectoryExists(app_path('Http/Controllers/Api/Admin'));
        File::ensureDirectoryExists(app_path("Http/Requests/Admin/{$modelName}"));
        File::ensureDirectoryExists(app_path('Http/Resources'));

        File::put($servicePath, $this->serviceTemplate($modelName));
        File::put($controllerPath, $this->controllerTemplate($modelName));
        File::put($storeRequestPath, $this->storeRequestTemplate($modelName));
        File::put($updateRequestPath, $this->updateRequestTemplate($modelName));
        File::put($resourcePath, $this->resourceTemplate($modelName));

        $this->info("CRUD stubs {$modelName} berhasil dibuat:");
        $this->line("- app/Services/{$modelName}Service.php");
        $this->line("- app/Http/Controllers/Api/Admin/{$modelName}Controller.php");
        $this->line("- app/Http/Requests/Admin/{$modelName}/Store{$modelName}Request.php");
        $this->line("- app/Http/Requests/Admin/{$modelName}/Update{$modelName}Request.php");
        $this->line("- app/Http/Resources/{$modelName}Resource.php");
    }

    protected function serviceTemplate(string $modelName): string
    {
        $var = $this->camel($modelName);
        return strtr(<<<'PHP'
<?php

namespace App\Services;

use App\Models\__MODEL__;

class __MODEL__Service
{
    public function getAll()
    {
        return __MODEL__::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return __MODEL__::findOrFail($id);
    }

    public function create(array $data)
    {
        return __MODEL__::create($data);
    }

    public function update(int $id, array $data)
    {
        $__VAR__ = $this->findById($id);
        $__VAR__->update($data);

        return $__VAR__;
    }

    public function delete(int $id)
    {
        $__VAR__ = $this->findById($id);
        $__VAR__->delete();

        return true;
    }
}
PHP, [
            '__MODEL__' => $modelName,
            '__VAR__' => $var,
        ]);
    }

    protected function controllerTemplate(string $modelName): string
    {
        $var = $this->camel($modelName);

        return strtr(<<<'PHP'
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\__MODEL__\Store__MODEL__Request;
use App\Http\Requests\Admin\__MODEL__\Update__MODEL__Request;
use App\Http\Resources\__MODEL__Resource;
use App\Services\__MODEL__Service;

class __MODEL__Controller extends Controller
{
    protected __MODEL__Service $service;

    public function __construct(__MODEL__Service $__SERVICE_VAR__)
    {
        $this->service = $__SERVICE_VAR__;
    }

    public function index()
    {
        $__VAR__ = $this->service->getAll();

        return response()->json([
            'success' => true,
            'message' => '__MODEL__ list retrieved successfully',
            'data' => __MODEL__Resource::collection($__VAR__),
            'meta' => [
                'current_page' => $__VAR__->currentPage(),
                'last_page' => $__VAR__->lastPage(),
                'per_page' => $__VAR__->perPage(),
                'total' => $__VAR__->total(),
            ],
        ]);
    }

    public function store(Store__MODEL__Request $request)
    {
        $__VAR__ = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => '__MODEL__ created successfully',
            'data' => new __MODEL__Resource($__VAR__),
        ]);
    }

    public function show(string $id)
    {
        $__VAR__ = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => '__MODEL__ retrieved successfully',
            'data' => new __MODEL__Resource($__VAR__),
        ]);
    }

    public function update(Update__MODEL__Request $request, string $id)
    {
        $__VAR__ = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => '__MODEL__ updated successfully',
            'data' => new __MODEL__Resource($__VAR__),
        ]);
    }

    public function destroy(string $id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => '__MODEL__ deleted successfully',
        ]);
    }
}
PHP, [
            '__MODEL__' => $modelName,
            '__VAR__' => $var,
            '__SERVICE_VAR__' => $var . 'Service',
        ]);
    }

    protected function storeRequestTemplate(string $modelName): string
    {
        return strtr(<<<'PHP'
<?php

namespace App\Http\Requests\Admin\__MODEL__;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class Store__MODEL__Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TODO: Sesuaikan rules validasi sesuai kolom tabel __TABLE__
        ];
    }
}
PHP, [
            '__MODEL__' => $modelName,
            '__TABLE__' => $this->snakePlural($modelName),
        ]);
    }

    protected function updateRequestTemplate(string $modelName): string
    {
        return strtr(<<<'PHP'
<?php

    namespace App\Http\Requests\Admin\__MODEL__;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

    class Update__MODEL__Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TODO: Sesuaikan rules validasi update sesuai kolom tabel __TABLE__
        ];
    }
}
PHP, [
            '__MODEL__' => $modelName,
            '__TABLE__' => $this->snakePlural($modelName),
        ]);
    }

    protected function resourceTemplate(string $modelName): string
    {
        return strtr(<<<'PHP'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class __MODEL__Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
PHP, [
            '__MODEL__' => $modelName,
        ]);
    }

    protected function camel(string $value): string
    {
        return Str::camel($value);
    }

    protected function snakePlural(string $value): string
    {
        return Str::snake(Str::pluralStudly($value));
    }
}
