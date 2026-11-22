const esbuild = require("esbuild");
const isWatch = process.argv.includes("--watch");

const builds = [
  {
    entryPoints: ["node_modules/libphonenumber-js/bundle/libphonenumber-js.min.js"],
    outfile: "admin/js/libphonenumber-js.min.js",
    minify: false
  }
];

async function build() {
  for (const config of builds) {
    await esbuild.build(config);
  }
  console.log("Build completed");
}

async function watch() {
  const watchers = builds.map((config) =>
    esbuild.context(config).then((ctx) => ctx.watch())
  );
  await Promise.all(watchers);
  console.log("Watching for changes...");
}

if (isWatch) {
  watch();
} else {
  build().catch(console.error);
}
