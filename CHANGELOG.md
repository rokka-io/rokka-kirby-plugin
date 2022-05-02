## 0.4.0 - 2022-04-02

- Support Kirby 3.6+ (but not older versions anymore, but you can still use 0.3.0 for that)

## 0.3.0 - 2022-01-26

- Use .gif as format, if source image is a gif.

## 0.2.0 - 2020-12-11 

- Added Rokka::generateRokkaUrl($file, $stack, $format) method.

## 0.1.0 - 2020-01-01

- Thumb methods (crop(), resize(), blur() and bw()) use now rokka as well
- Adjusted for Kirby 3. Doesn't work for kirby 2 anymore, use the "kirby-2" branch, if you need this.
- Uses Stack Variables for shorter URLs. It's recommended to run `https://yourkirbysite.com/_rokka/create-stacks` to 
  update your stacks.

## 0.0.5 - 2018-08-14

- The image: kirbytag while using this plugin now also honours caption and such attributes.

## 0.0.4 - 2018-04-19

- Based on the new TemplateHelper classes of rokka/client 1.3.0

## 0.0.3

- Changed `srcAttributes` to `getSrcAttributes`  and `backgroundImageStyle` to `getBackgroundImageStyle`.

## 0.0.2

- The config format for 'plugin.rokka.stacks.options', needs an 'options' for stack options and can also take 'resize'
  stack operation options.
- You can now define a default stack for images included by kirbytext
