This theme uses [grunt](http://gruntjs.com//) to compile javascript and Sass. Below are steps for getting set up.

##Installing node locally
If you don't have node.js installed locally, you'll have to install it.

If you're using a Mac and have homebrew installed, open up terminal and type ```brew install node```. Once it's finished, type ```node -v``` to confirm it's installed

If you don't have homebrew, you can use the node installer here: https://nodejs.org/en/.

##Installing node modules

To get going, open terminal and cd to ```themes/custom/msca_theme```

Once there, type ```npm install```. That will download a bunch of stuff to node_modules, which you can ignore.

##Compiling CSS and JS
Now type ```grunt``` into the terminal. This should compile your Sass and Javascript for you, and will start watching for CSS changes.

If you don't want to watch and only want to compile CSS, type ```grunt compile```.
