module.exports = function(grunt) {
    // Configuration goes here
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

    // Sass configuration
    sass: {
        dist: {
            options: {
                style: 'compressed'
            },
            files: {
                'style.css': 'sass/style.scss'
            }
        } 
    },

    // Autoprefixer to rewrite file with vendor prefixes
    autoprefixer: {
       // prefix the specified file
       single_file: {
         options: {
           // Target-specific options go here.
         },
         src: 'style.css',
         dest: 'style.css'
       }
     },

      // Watcher with live reload. With this on, you can use live reload in your browser to see live CSS changes.
      // See theme readme for details
      watch: {
      css: {
          files: ['sass/*.scss', 'sass/*/*.scss', 'sass/*/*/*.scss', 'sass/*/*/*/*.scss'],
          tasks: ['autoprefixer', 'sass'],
          options: {
            spawn: false,
            livereload: true
          }
        },
      js: {
        files: ['js/src/*.js'],
        tasks: ['concat', 'babel']
        }
      },


      concat: {
        options: {
          // Custom function to remove all export and import statements
          process: function (src) {
            return src.replace(/^(export|import).*/gm, '')
          }
        },
        dist: {
          src: [
            'js/dist/constants.js',
            'js/dist/base.js',
            'js/dist/bootstrap/tab.js',
            'js/dist/scripts.js',
            'js/dist/bootstrap/util.js',
            'js/dist/bootstrap/collapse.js',
            'js/src/bootstrap/carousel.js'
          ],
          dest: 'scripts.js'

        }
      },

      babel: {
        dist: {
          options: {
            "presets": [
              [
                "es2015",
                {
                  "modules": false,
                  "loose": true
                }
              ]
            ],
            "plugins": [
              "transform-es2015-modules-strip"
            ]
          },


          files: {
            'js/dist/constants.js': 'js/src/constants.js',
            'js/dist/base.js': 'js/src/base.js',
            'js/dist/menu.js': 'js/src/menu.js',
            'js/dist/stickybits.js': 'js/src/stickybits.js',
            'js/dist/scripts.js': 'js/src/scripts.js',
            'js/dist/bootstrap/util.js': 'js/src/bootstrap/util.js',
            'js/dist/bootstrap/tab.js': 'js/src/bootstrap/tab.js',
            'js/dist/bootstrap/collapse.js': 'js/src/bootstrap/collapse.js'
          }
        }
      }

    });


   // Where we tell Grunt we plan to use this plug-in.
   grunt.loadNpmTasks('grunt-contrib-sass');
   grunt.loadNpmTasks('grunt-autoprefixer');
   grunt.loadNpmTasks('grunt-contrib-watch');
   grunt.loadNpmTasks('grunt-contrib-concat');
   grunt.loadNpmTasks('grunt-babel');

   // Where we tell Grunt what to do when we type "grunt" into the terminal. By default this sets up watch.
   grunt.registerTask('default', ['babel', 'concat', 'sass', 'autoprefixer', 'watch']);

   // Sets up task so that typing "grunt compile" will compile CSS without watching
   grunt.registerTask('compile', ['sass', 'autoprefixer']);

};
