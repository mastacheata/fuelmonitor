@servers([ 'remote' => 'fuelmonitor@ymoq.de'])

@setup
if ( ! isset($repo) )
{
    throw new Exception('--repo must be specified');
}

if ( ! isset($base_dir) )
{
    throw new Exception('--base_dir must be specified');
}

$branch      = isset($branch) ? $branch : 'master';
$repo_array  = explode('/', $repo);
$repo_name   = array_pop($repo_array);
$repo        = 'https://api.github.com/repos/' . $repo . '/tarball/' . $branch;
$release_dir = $base_dir . '/source';
$current_dir = $base_dir . '/webroot';
$release     = date('YmdHis');
$env         = isset($env) ? $env : 'staging';
@endsetup

@macro('deploy', [ 'on' => 'remote', ])
fetch_repo
run_composer
update_symlinks
update_permissions
clean_old_releases
@endmacro

@task('fetch_repo')
[ -d {{ $release_dir }} ] || mkdir {{ $release_dir }};
cd {{ $release_dir }};

# Make the release dir
mkdir {{ $release }};

# Download the tarball
echo 'Fetching project tarball';
curl -H "Authorization: token {{ $token }}" -sLo {{ $release }}.tar.gz {{ $repo }};

# Extract the tarball
echo 'Extracting tarball';
tar --strip-components=1 -zxf {{ $release }}.tar.gz -C {{ $release }};

# Purge temporary files
echo 'Purging temporary files';
rm -rf {{ $release }}.tar.gz;
@endtask

@task('run_composer')
echo 'Installing composer dependencies';
cd {{ $release_dir }}/{{ $release }};
composer install --prefer-dist --no-scripts -q -o;
@endtask

@task('update_symlinks')
echo 'Updating symlinks';

# Symlink the latest release to the current directory
echo 'Linking current release';
ln -nfs source/{{ $release  }} {{ $current_dir }};
@endtask

@task('update_permissions')
cd {{ $release_dir }}/{{ $release }};
echo 'Updating directory permissions';
find . -type d -exec chmod 775 {} \;
echo 'Updating file permissions';
find . -type f -exec chmod 664 {} \;
@endtask

@task('clean_old_releases')
echo 'Purging old releases';
# This will list our releases by modification time and delete all but the 5 most recent.
ls -dt {{ $release_dir }}/* | tail -n +8 | xargs -d '\n' rm -rf;
@endtask

@task('update_version')
echo 'Updating version number';
sed -ie "s/\"version\"\(.*\):.*$/\"version\"\1: {{ \$build }}/g" preferences-*.json
@endtask
