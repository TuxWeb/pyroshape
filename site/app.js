import { content } from './content.js?v=footer-affiliation';

const languageLinks = [...document.querySelectorAll('[data-language]')];
const phaseButtons = [...document.querySelectorAll('[data-phase]')];
const copyButtons = [...document.querySelectorAll('[data-copy]')];
const labels = [...document.querySelectorAll('[data-text]')];
const ariaLabels = [...document.querySelectorAll('[data-label]')];
const workbench = document.querySelector('[data-phase-view]');
const phaseDescription = document.querySelector('#phase-description');
const phaseResult = document.querySelector('#phase-result');
const copyStatus = document.querySelector('#copy-status');
const phases = {
  access: ['sessionAccess', 'sessionAccessResult'],
  refresh: ['sessionRefresh', 'sessionRefreshResult'],
  logout: ['sessionLogout', 'sessionLogoutResult'],
};
let language;
let currentPhase = 'access';

function activeLanguage() {
  return new URL(location.href).searchParams.get('lang') === 'it' ? 'it' : 'en';
}

function renderPhase() {
  const [description, result] = phases[currentPhase];
  const copy = content[language];
  workbench.dataset.phaseView = currentPhase;
  phaseDescription.textContent = copy[description];
  phaseResult.textContent = copy[result];

  for (const button of phaseButtons) {
    button.setAttribute('aria-pressed', String(button.dataset.phase === currentPhase));
  }
}

function renderLanguage() {
  language = activeLanguage();
  const copy = content[language];
  document.documentElement.lang = language;
  document.title = copy.title;
  document.querySelector('meta[name="description"]').content = copy.description;
  document.querySelector('meta[property="og:title"]').content = copy.title;
  document.querySelector('meta[property="og:description"]').content = copy.description;

  for (const element of labels) {
    const translation = copy[element.dataset.text];
    if (typeof translation === 'string') element.textContent = translation;
  }
  for (const element of ariaLabels) {
    element.setAttribute('aria-label', copy[element.dataset.label]);
  }
  for (const link of languageLinks) {
    const url = new URL(location.href);
    url.searchParams.set('lang', link.dataset.language);
    link.href = url.href;
    if (link.dataset.language === language) {
      link.setAttribute('aria-current', 'true');
    } else {
      link.removeAttribute('aria-current');
    }
  }

  renderPhase();
}

for (const link of languageLinks) {
  link.addEventListener('click', (event) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    if (link.dataset.language === language) return;
    history.pushState(null, '', link.href);
    renderLanguage();
  });
}
addEventListener('popstate', renderLanguage);
addEventListener('hashchange', renderLanguage);

for (const button of phaseButtons) {
  button.addEventListener('click', () => {
    currentPhase = button.dataset.phase;
    renderPhase();
  });
}

for (const button of copyButtons) {
  let timer;
  button.addEventListener('click', async () => {
    const code = document.getElementById(button.dataset.copy);
    let message;
    try {
      await navigator.clipboard.writeText(code.textContent);
      message = content[language].copied;
    } catch {
      const range = document.createRange();
      range.selectNodeContents(code);
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      message = content[language].copyFailed;
    }
    copyStatus.textContent = message;
    button.textContent = message;
    clearTimeout(timer);
    timer = setTimeout(() => {
      button.textContent = content[language].copy;
    }, 2200);
  });
}

renderLanguage();
