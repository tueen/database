import DefaultTheme from 'vitepress/theme';
import LlmSkillCopy from './components/LlmSkillCopy.vue';

export default {
  extends: DefaultTheme,
  enhanceApp({ app }: { app: any }) {
    app.component('LlmSkillCopy', LlmSkillCopy);
  },
};
