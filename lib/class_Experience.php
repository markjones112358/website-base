<?php

/**
 * Given a file path, extract and clean the contents.
 * @param  String $path, file to open and read.
 * @param  Int $limit, truncate the returned text to the given num of chars.
 * @return String
 */
function retrieve_text($path, $limit=False) {
    if ($path == False) return False;
    else {
        $out = file_get_contents($path);
        $out = strip_tags($out);
        $out = htmlentities($out, ENT_QUOTES, 'UTF-8');
        $out = str_replace("\n", '<br>', $out);
        return $out;
    }
}

/**
 * Limit the length of a string without cutting words.
 * @param  String $text  String to be length limited
 * @param  Int $limit    Number of characters to limit
 * @param  String $trail Characters to insert if limit is reached
 * @return String
 */
function limit_text($text, $limit, $trail="...") {
    $out = str_replace("<br>", ' ', $text);
    $words = explode(" ", $out);
    $out = "";
    $count = 0;
    $spaceflag = False;
    $delimiter = "";
    foreach ($words AS $word) {
        $wordlen = strlen($word);
        if ($wordlen > 0) {
            $spaceflag = False;
            if ($count + $wordlen + 1 <= $limit) {
                $out .= $delimiter . $word;
                $count += $wordlen + 1;
                $delimiter = " ";
            }
            else {
                $out .= $trail;
                break;
            }
        }
        elseif ($spaceflag == False) {
            $spaceflag = True;
            $out .= "<br>";
            $delimiter = "";
        }
    }
    return $out;
}

function unpack_name($filename) {
    $out = Array();
    $lastDigitPos = 0;
    $strlen = strlen($filename);
    for ($i = 0; $i < $strlen; $i++) {
        if (is_numeric($filename[$i])) $lastDigitPos = $i + 1;
        elseif (ctype_alpha($filename[$i])) break;
    }
    $date = substr($filename, 0, $lastDigitPos);
    $date = str_replace('-', '', $date);
    $date = str_replace('_', '', $date);
    $date = str_replace(' ', '', $date);
    $datelen = strlen($date);
    if ($datelen == 8) {
        if (intval(substr($date, 0, 4)) > 1970) {
            $out['year'] = substr($date, 0, 4);
            $out['month'] = substr($date, 4, 2);
            $out['day'] = substr($date, 6, 2);
        }
        elseif (intval(substr($date, 4, 8)) > 1970) {
            $out['day'] = substr($date, 0, 2);
            $out['month'] = substr($date, 2, 2);
            $out['year'] = substr($date, 4, 4);
        }
        $out['date'] = new DateTime($out['year'].'-' . $out['month'] . '-' . $out['day'] . ' 0:0:0');
        $out['dateFormatter'] = 'jS M Y';
    }
    elseif ($datelen == 6) {
        if (intval(substr($date, 0, 4)) > 1970) {
            $out['year'] = substr($date, 0, 4);
            $out['month'] = substr($date, 4, 2);
        }
        elseif (intval(substr($date, 2, 6)) > 1970) {
            $out['month'] = substr($date, 0, 2);
            $out['year'] = substr($date, 2, 2);
        }
        $out['date'] = new DateTime($out['year'].'-' . $out['month'] . '-1 0:0:0');
        $out['dateFormatter'] = 'M Y';
    }
    elseif ($datelen == 4) {
        $out['year'] = substr($date, 0, 4);
        $out['date'] = new DateTime($out['year'].'-1-1 0:0:0');
        $out['dateFormatter'] = 'Y';
    }
    //else trigger_error('Couldnt understand syntax of ' . $filename);

    //Get title-description from $rest
    $rest = substr($filename, $lastDigitPos);

    while (strpos($rest, '-') === 0) $rest = substr($rest, 1);
    while (strpos($rest, '_') === 0) $rest = substr($rest, 1);

    $rest = str_replace('-', ' ', str_replace('_', ' ', $rest));
    $out['title'] = $rest;
    #$parts = explode('-', $rest, 2);
    #$out['title'] = $parts[0];
    #if (isset($parts[1])) $out['description'] = $parts[1];

    return $out;
}


class Node {
    // Represents either a file or a folder, therefore called node.

    public $date;           // DateTime object representing the node
    public $dateFormatter;  // Formatting string for showing the date
    public $year;           // Year of node
    public $month;          // Month of node
    public $day;            // Day of node
    public $title;          // Title as extracted from name
    public $description;    // Description extracted from name
    public $url;            // URL for this node (may be a file may be a folder)
    public $path;           // Full path on disk
    public $name;           // Name of folder or file

    public function __construct($location) {

        // Detect if location is url or path
        if (strpos($location, PATH_WATCH) !== FALSE) {
            $this->path = $location;
            $this->url = clean_path(path2url($location));
        }
        else {
            $this->url = $location;
            $this->path = clean_path(url2path($location));
        }


        if (substr($this->url, -1) == '/') {
            $this->name = urldecode(end(explode('/',substr($this->url,0,-1))));
        }
        else{
            $this->name = urldecode(end(explode('/', $this->url)));
        }

        $tmp = unpack_name($this->name);
        foreach ($tmp as $key => $value) $this->$key = $value;

        if ($this->date == False) {
            $this->date = new DateTime(date('c', filemtime($this->path)));

            $this->dateFormatter = 'jS M Y';
            $this->year = $this->date->format('Y');
            $this->month = $this->date->format('M');
            $this->day = $this->date->format('d');
        }
    }
}

class File extends Node {

    public $ext;
    public $path_remote;
    public $path_local;
    public $folder_remote;
    public $folder_local;
    public $url_folder;
    public $url_file;
    public $markdown_parser;

    public function __construct($location, $ext=False) {
        if ($ext == False) $this->ext = File::extension($location);
        parent::__construct($location);
        $this->filename_remote = $location;
        $this->filename_local = $_SERVER['DOCUMENT_ROOT'] . '/' . substr($this->url_folder(), 1) . $this->name;
        $this->folder_remote = str_replace($this->name, '', $this->filename_remote);
        $this->folder_local = str_replace($this->name, '', $this->filename_local);
        $this->url_folder = str_replace($this->name, '', $this->url);
        $this->url_file = $this->url;
        $this->markdown_parser = False;
    }

    public static function extension($location) {
        // Return the file extension for the file in the given location
        return strtolower(end(explode('.', $location)));
    }

    public static function create($location) {
        $ext = File::extension($location);

        $images = Array('jpg',
                        'png',
                        'gif');
        $markup = Array('html',
                        'txt',
                        'md');
        $code = Array('py',
                      'sh',
                      'rb',
                      'hs',
                      'class',
                      'sql',
                      'sci',
                      'pl',
                      'm',
                      'gpi',
                      'plt',
                      'c',
                      'ada',
                      'asp',
                      'autoconf',
                      'autohotkey',
                      'bash',
                      'awk',
                      'bibtex',
                      'c',
                      'cmake',
                      'coffeescript',
                      'cpp',
                      'csharp',
                      'css',
                      'd',
                      'diff',
                      'fortran',
                      'java',
                      'js',
                      'tex',
                      'lua',
                      'php');

        if (in_array($ext, $images)) return new Image($location, $ext);
        elseif (in_array($ext, $markup)) return new Text($location, $ext);
        elseif (in_array($ext, $code)) return new Code($location, $ext);
        elseif ($ext == 'comments') return new Comments($location, $ext);
        else return False;
    }

    /**
     * Return the folder (in relative url format) where this file is contained
     * @return [string] [folder path]
     */
    public function url_folder() {
        return str_replace($this->name, '', path2url($this->path));
    }

    public function path_folder() {
        return str_replace($this->name, '', $this->path);
    }

    // Render function must be overriden by subclassed filetype
    public function render() {
        trigger_error('Unknown file type');
    }
}

class Image extends File {

    public function __construct($location) {
        parent::__construct($location);
    }

    public function render() {
        if ($this->cached() == False) $this->cache();
        return $this->as_thumbnail();
    }

    public function as_thumbnail() {
        $c = new Content();
        $attrs = array('href'=>$this->url(),
                'class' => 'fancybox photo',
                'rel' => 'gallery',
                'title' => $this->name);

        if (strpos($this->url, '_fr') != False) {
            $attrs['class'] .= ' fr';
            $attrs['style'] = 'clear: right';
        }
        elseif (strpos($this->url, '_fl.') != False) {
            $attrs['class'] .= ' fl';
            $attrs['style'] = 'clear: left';
        }

        $c->append(img( $this->url(True),""));
        $c->wrap('a',$attrs);

        return $c;
    }

    public function url($thumbnail=False) {
        return $this->url_folder . $this->filename_cached($thumbnail);
    }

    public function filename_cached($thumbnail=False) {
        $out = md5_file($this->filename_remote);
        if ($thumbnail) $out .= '_thumb';
        return $out .= '.' . $this->ext;
    }

    public function cached($thumbOnly=False) {
        $path = $this->folder_local;
        if (file_exists($path . $this->filename_cached(True)) == False) return False;
        if ($thumbOnly == False && file_exists($path . $this->filename_cached()) == False) return False;
        return True;
    }

    public function cache($thumbOnly=False) {
        if (file_exists($this->folder_local) == False) {
            mkdir($this->folder_local, 0765, True);
        }
        if (file_exists($this->folder_local . $this->filename_cached(True)) == False) $this->create_thumbnail();
        if ($thumbOnly == False && file_exists($this->folder_local . $this->filename_cached()) == False) $this->create_fullImage();
    }

    public function create_thumbnail() {
        $cmd = "convert '" . $this->filename_remote . "' -thumbnail '256' '" . $this->folder_local . $this->filename_cached(True) . "'";
        exec($cmd);
    }

    public function create_fullImage() {
        $cmd =  "convert '" . $this->filename_remote . "' -resize '1024x768>' '" . $this->folder_local . $this->filename_cached() . "'";
        exec($cmd);
    }

}


class Comments extends File {
    public function __construct($location) {
        parent::__construct($location);
    }

    public function render() {
        $data = file_get_contents($this->path);
        $attrs = Array('class'=>'comments');
        $disqus = new Content();
        $disqus->div('', array('id'=>'disqus_thread'));
        $disqus->script_block("
            var disqus_shortname = '".trim(preg_replace('/\s\s+/', ' ', $data))."'; // required: replace example with your forum shortname
            (function() {
                var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
                dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
                (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
            })();
        ");
        $disqus->block('noscript', 'Please enable JavaScript to view the ' . href('comments powered by Disqus.','http://disqus.com/?ref_noscript'));
        $disqus->href("http://disqus.com", 'comments powered by ' . span('Disqus', array('class'=>'logo-disqus')), array('class'=>'dsq-brlink'));
        return div($disqus, $attrs);
    }
}

class Text extends File {

    public function __construct($location) {
        parent::__construct($location);
    }

    public function render() {
        $data = file_get_contents($this->path);
        $attrs = Array();
        if ($this->ext != 'txt') {
            if ($this->ext == 'md') {
                $attrs['class'] = 'markdown';
                $parsedown = new Parsedown();
                $data = $parsedown->parse($data);
            }
            else $attrs['class'] = 'htmlBox';
        }
        else $attrs['class'] = 'm20_0';
        return div($data, $attrs);
    }
}

class Code extends File {

    public function __construct($location) {
        parent::__construct($location);
    }

    public function render() {
        $data = file_get_contents($this->path);
        $attrs = Array('class'=>'l code bdr_r8');
        switch ($this->ext) {
            case 'py':
                $language = 'python';
                break;

            case 'sh':
                $language = 'bash';
                break;

            case 'rb':
                $language = 'ruby';
                break;

            case 'hs':
                $language = 'haskell';
                break;

            case 'class':
                $language = 'java';
                break;

            case 'sql':
                $language = 'mysql';
                break;

            case 'sci':
                $language = 'scilab';
                break;

            case 'pl':
                $language = 'perl';
                break;

            case 'm':
                $language = 'octave';
                break;

            case 'gpi':
            case 'plt':
                $language = 'gnuplot';
                break;

            case 'js':
                $language = 'javascript';
                break;

            case 'tex':
                $language = 'latex';
                break;

            default:
                $language = $this->ext;
                break;
        }
        $geshi = new GeSHi($data, $language);
        $geshi->enable_keyword_links(false);
        $geshi->enable_classes();
        return div($geshi->parse_code(), $attrs);
    }
}

class Experience extends Node {

    public $files;
    public $thumbnail;
    public $last_modified;
    public $flags;
    public $type;

    public function __construct($location) {
        $this->thumbnail = False;
        $this->files = Array();
        $this->flags = Array();
        parent::__construct($location);
        if (count(array_filter(explode('/', $this->url))) == 2) {
            $this->type  = array_values(array_filter(explode('/', $this->url)))[0];
        }
        else $this->type = False;
        $this->last_modified = last_modified($this->path);
        $this->populate_files();
        // Use the oldest known date of the folder
        foreach ($this->files AS $file) {
            if ($file->date < $this->date) $this->date = $file->date;
        }
    }

    public function render() {
        $c = new Content();
        foreach ($this->files AS $file) {
            if (strpos(strtolower($file->name), 'noshow') == False &&
                strtolower($file->name) != 'desc.txt' &&
                strtolower($file->name) != 'description.txt') {
                $c->append($file->render());
            }
            else {
                // Make sure a cached version of any images with the noshow
                // flag get generated
                if (strpos(strtolower($file->name), 'noshow') !== False &&
                    get_class($file) == 'Image' &&
                    $file->cached() == False) {
                    $file->cache();
                }
            }

        }
        return $c;
    }

    public function populate_files($type=False) {
        //Supported filetypes
        $extensions = Array('md',
                            'html',
                            'txt',
                            'jpg',
                            'png',
                            'gif',
                            'py',
                            'sh',
                            'rb',
                            'php',
                            'hs',
                            'c',
                            'cpp',
                            'sql',
                            'class',
                            'scala',
                            'sci',
                            'pl',
                            'm',
                            'go',
                            'gpi',
                            'plt',
                            'comments');
        $files = $this->get_filesByExtension($extensions, True);
        foreach ($files as $file) {
            $fileObj = File::create(clean_path($file));
            if ($fileObj !== False) {
                array_push($this->files, $fileObj);
            }
        }
    }

    public function get_filesByExtension($extensions, $addPath=False) {
        $out = Array();
        foreach (scandir($this->path) AS $file) {
            if (strpos($file, '.') === False) {
                // Detect any flag files and push to experience flag array
                array_push($this->flags, $file);
            }
            else {
                foreach ($extensions AS $extension) {
                    if (strpos(strtolower($file), '.' . strtolower($extension)) != False) {
                        array_push($out, $file);
                        break;
                    }
                }
            }
        }
        asort($out);

        if ($addPath) return $this->pathPrependor($out);
        else return $out;
    }

    public function pathPrependor($filenames) {
        return array_map(function ($x) {
            return $this->path .  $x;
        }, $filenames);
    }

    public function displayBox() {
        if (function_exists('user_displayBox')) {
            return user_displayBox($this->title,
                                   limit_text(retrieve_text($this->get_description()), 160),
                                   $this->get_thumbnail(),
                                   clean_path($this->url),
                                   $this->date);
        }
        else {
            $box = new Content();
            $box->span($this->title, array('class'=>'c5', 'style'=>'display: inline-block; width: 100%'));
            $thumbnail = $this->get_thumbnail();
            $box->img($thumbnail, '');
            $wrapAttrs = array('href' => clean_path($this->url),
                               'class' => 'expBox mainFont bg_c4');
            $box->wrap('a',$wrapAttrs);
            //Find a thumbnail for the experience
            return $box;
        }
    }

    public function get_description() {

        $textfiles = $this->files_byExtension('txt');
        $desc_file = False;

        if (count($textfiles) > 0) {
            // Look first for a dedicated description file
            foreach ($textfiles as $file) {
                $lower = strtolower($file->name);
                if ($lower == 'desc.txt' || $lower == 'description.txt') {
                    $desc_file = $file->path;
                }
            }
        }
        else {
            // Take text from the first text file in the folder
            $textfiles = $this->files_byClass('Text');
            if (count($textfiles) > 0) {
                // Assume the first file is most suited as containing
                // the description and chop the first 160 characters.
                $desc_file = $textfiles[0]->path;
            }
        }

        return $desc_file;
    }



    /**
     * Return a subset of $this->files (containing File Objects) based
     * on the provided classname
     * @param  String $classname
     * @return Array()
     */
    public function files_byClass($classname) {
        $out = Array();
        foreach ($this->files AS $file) {
            if (get_class($file) == $classname) array_push($out, $file);
        }
        return $out;
    }

    public function files_byExtension($extensions) {
        $out = Array();
        if (gettype($extensions) == 'string') $extensions = Array($extensions);
        foreach ($this->files AS $file) {
            if (in_array($file->ext, $extensions)) array_push($out, $file);
        }
        return $out;
    }

    /**
     * Retrieve the name of the path to the image that will be used as the
     * thumbnail for this experience
     * @return [String || False] Path to the image to use as the thumbnail or
     * False if no suitable image found.
     */
    public function get_thumbnail() {
        if ($this->thumbnail == False) {
            $maxChars = 0;
            $thumbImg = False;
            $thumb = False;

            $images = $this->files_byExtension(Array('jpg', 'png', 'gif'));
            foreach ($images as $image) {
                if (strpos($image->name, 'thumb') !== False) {
                    $thumb = $image;
                    break;
                }
                elseif (strpos($image->name, 'logo') !== False) {
                    $thumb = $image;
                    break;
                }
                $strlen = strlen($image->name);
                $count = 0;
                for ($i = 0; $i < $strlen; $i++) {
                    if (ctype_alpha($image->name[$i])) $count++;
                }
                if ($count > $maxChars) {
                    $maxChars  = $count;
                    $thumb = $image;
                }
            }

            if ($thumb) {
                // $thumb = new Image(clean_path($this->path . $thumbImg));
                if ($thumb->cached(True) == False) $thumb->cache(True);
                $this->thumbnail = $thumb->url(True);
            }
            else $this->thumbnail = url_static() . '/noPhoto.png';

            return $this->thumbnail;
        }
    }
}

class ExperienceList {

    public $experiences;
    public $path_base;

    public function __construct($path_base=False) {

        // Determine where and how deep we are in the subfolders
        $path = urlToArray($path_base);
        $this->experiences = Array();

        // We only load experiences from subfolders so load as appropriate

        // If we're at base (path is empty) then add all experience types to array
        $subdirs = array_map(function ($x) {return str_replace('/', '', $x);},get_subdirs('/'));

        foreach ($subdirs AS $type) $this->experiences[$type] = Array();
        // if (count($path) == 0) {
        //     $subdirs = array_map(function ($x) {return str_replace('/', '', $x);},get_subdirs('/'));
        //     foreach ($subdirs AS $type) $this->experiences[$type] = Array();
        // }
        // else {
        //     // Otherwise add only the experience type that we are in
        //     $this->experiences[$path[1]] = Array();
        // }

        // If we're in a specific experience with an experience type, only load that experience

        if (count($path) == 2 || count(get_subdirs($path_base)) == 0) {
            array_push($this->experiences[$path[1]], new Experience($path_base));
        }
        else {
            // Otherwise load add experiences of each type in the experience array
            foreach ($this->experiences as $type => $none) {
                $experiencePath = '/' . $type . '/';
                $experiences = get_subdirs($experiencePath);
                $index = array_map(function ($x) use ($experiencePath) {return str_replace($experiencePath, '', $x);}, $experiences);
                foreach ($index as $name) {
                    array_push($this->experiences[$type], new Experience(clean_path($experiencePath . $name)));
                }
            }
        }
    }

    public function get_experience($type, $name) {
        foreach ($this->experiences[$type] as $experience) {
            if ($experience->name == $name) return $experience;
        }
        return False;
    }

    public function types() {
        return array_keys($this->experiences);
    }

    public function get_all() {
        $all = Array();
        foreach ($this->types() AS $expType) $all = array_merge($all, $this->experiences[$expType]);
        return $all;
    }

    public function mostRecent($num) {
        return first($num, ExperienceList::ordered_byDate($this->get_all()));
    }

    public static function ordered_byDate($array) {
        return ExperienceList::sorted($array);
    }


    public static function comaprer($a, $b) {
        if ($a->date < $b->date) return True;
        else return False;
    }


    public static function sorted($input) {
        usort($input, 'ExperienceList::comaprer');
        return $input;
    }

}
