<?php

namespace Appzcoder\CrudGenerator\Commands;

use File;
use Illuminate\Console\Command;

class CrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:generate
                            {name : The name of the Crud.}
                            {--fields= : Fields name for the form & model.}
                            {--route=yes : Include Crud route to routes.php? yes|no.}
                            {--pk=id : The name of the primary key.}
                            {--view-path= : The name of the view path.}
                            {--namespace= : Namespace of the controller.}
                            {--route-group= : Prefix of the route group.}
                            {--localize=yes : Localize the generated files? yes|no. }
                            {--locales=en : Locales to create lang files for.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Crud including controller, model, views & migrations.';

    /** @var string  */
    protected $name = '';
    protected $routeName = '';
    protected $routeGroup = '';

    /** @var string  */
    protected $controller = '';
    protected $controllerName = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $this->name = $name;
        $migrationName = str_plural(snake_case($name));
        $tableName = $migrationName;
        $word = str_replace('_', ' ', $name);
        $viewName = snake_case($word, '-');
        $humanName = ucwords(str_singular($word));
        $modelName = ucwords(camel_case(str_singular($name)));

        $routeGroup = $this->option('route-group');
        $this->routeGroup = $routeGroup;
        $this->routeName = ($routeGroup) ? $routeGroup . '/' . snake_case($name, '-') : snake_case($name, '-');

        $namespace = $this->option('namespace');
        $controllerNamespace = $namespace ? $namespace . '\\' : '';
        $this->controllerName = $modelName . 'Controller';

        $fields = $this->option('fields');
        $primaryKey = $this->option('pk');
        $viewPath = $this->option('view-path');

        $fieldsArray = explode(',', $fields);
        $requiredFields = '';
        $requiredFieldsStr = '';

        foreach ($fieldsArray as $item) {
            $fillableArray[] = preg_replace("/(.*?):(.*)/", "$1", trim($item));

            $itemArray = explode(':', $item);
            $currentField = trim($itemArray[0]);
            $requiredFieldsStr .= (isset($itemArray[2])
                && (trim($itemArray[2]) == 'req'
                    || trim($itemArray[2]) == 'required'))
            ? "'$currentField' => 'required', " : '';
        }

        $commaSeparetedString = implode("', '", $fillableArray);
        $fillable = "['" . $commaSeparetedString . "']";

        $requiredFields = ($requiredFieldsStr != '') ? "[" . $requiredFieldsStr . "]" : '';

        $localize = $this->option('localize');
        $locales = $this->option('locales');


        $this->call('crud:controller', ['name' => $controllerNamespace . $this->controllerName, '--crud-name' => $name, '--model-name' => $modelName, '--view-path' => $viewPath, '--required-fields' => $requiredFields, '--route-group' => $routeGroup]);
        $this->call('crud:model', ['name' => $modelName, '--fillable' => $fillable, '--table' => $tableName]);
        $this->call('crud:migration', ['name' => $migrationName, '--schema' => $fields, '--pk' => $primaryKey]);
        $this->call('crud:view', ['name' => $name, '--fields' => $fields, '--view-path' => $viewPath, '--route-group' => $routeGroup, '--localize' => $localize]);
        if($this->option('localize') == 'yes') {
            $this->call('crud:lang', ['name' => $name, '--fields' => $fields, '--locales' => $locales]);
        }
        // For optimizing the class loader
        $this->callSilent('optimize');

        // Updating the Http/routes.php file
        $routeFile = app_path('Http/routes.php');
        if (file_exists($routeFile) && (strtolower($this->option('route')) === 'yes')) {
            $this->controller = ($controllerNamespace != '') ? $controllerNamespace . $modelName . 'Controller' : $modelName . 'Controller';

            if (\App::VERSION() >= '5.2') {
                $isAdded = File::append($routeFile,
                    "\nRoute::group([".($namespace ? "'namespace' => '{$namespace}', " : '') . ($routeGroup ? "'prefix' => '{$routeGroup}', " : '') . "'middleware' => 'web'], function () {"
                    . "\n\t" . implode("\n\t", $this->addRoutes())
                    . "\n});"
                );
            } else {
                $isAdded = File::append($routeFile, "\n".implode("\n", $this->addRoutes()));
            }

            if ($isAdded) {
                $this->info('Crud/Resource route added to ' . $routeFile);
            } else {
                $this->info('Unable to add the route to ' . $routeFile);
            }
        }
    }

    protected function addRoutes() {
        return ["Route::resource('" . ($this->routeGroup ? $this->name : $this->routeName) . "', '" . $this->controllerName . "');"];
    }
}
