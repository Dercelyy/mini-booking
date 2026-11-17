import { createApp } from "vue";
import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";
import { definePreset } from "@primeuix/themes";
import App from "./App.vue";
import "./style.css";
const theme = definePreset(Aura, {
  semantic: {
    primary: {
      50: "#effaf7",
      100: "#d6f1e7",
      200: "#ade3cf",
      300: "#76cfb2",
      400: "#40b48e",
      500: "#208567",
      600: "#176c54",
      700: "#155642",
      800: "#154536",
      900: "#12392e",
      950: "#072018",
    },
    colorScheme: {
      light: {
        primary: {
          color: "{primary.600}",
          contrastColor: "#ffffff",
          hoverColor: "{primary.700}",
          activeColor: "{primary.800}",
        },
      },
    },
  },
});

createApp(App)
  .use(PrimeVue, {
    theme: { preset: theme, options: { darkModeSelector: false } },
  })
  .mount("#app");
