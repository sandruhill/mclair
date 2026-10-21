/**
 * Conteúdo fixo da página /servicos/assessoria-de-imprensa/rio-de-janeiro/,
 * usado no HTML visível e no FAQPage do JSON-LD. As matérias citadas saíram
 * no Extra e estão nos cases Globo Leilões e VIP Leilões, em
 * mclair.com.br/cases/. Nenhum cliente com sede no Rio aparece nos cases
 * publicados, e a página diz isso.
 */
import type { Pergunta } from './assessoria-imprensa';

export const URL_ASSESSORIA_RJ = '/servicos/assessoria-de-imprensa/rio-de-janeiro/';

export const SEO_ASSESSORIA_RJ = {
  title: 'Assessoria de Imprensa no Rio de Janeiro | Mclair',
  h1: 'Assessoria de imprensa no Rio de Janeiro',
  description:
    'Assessoria de imprensa no Rio de Janeiro para empresas de energia, cultura, turismo, eventos e saúde. Clientes da Mclair já saíram no Extra. Atendimento a distância.',
  keywords:
    'assessoria de imprensa rio de janeiro, assessoria de imprensa no rio de janeiro, assessoria de imprensa rj, assessoria de imprensa em rj, agência de assessoria de imprensa no rio de janeiro, mclair',
};

export const WA_ASSESSORIA_RJ =
  'https://wa.me/5511988600372?text=' +
  encodeURIComponent('Olá Mclair! Vim pela página de Assessoria de Imprensa no Rio de Janeiro e quero conversar sobre a minha empresa.');

export const FAQ_ASSESSORIA_RJ: Pergunta[] = [
  {
    pergunta: 'Quanto custa contratar assessoria de imprensa no Rio de Janeiro?',
    resposta:
      'Não existe tabela única. O valor muda com o número de porta-vozes, o ritmo de pautas, a mistura entre imprensa do Rio e nacional e extras como media training. A Mclair conversa primeiro, entende o momento da empresa e só então manda a proposta.',
  },
  {
    pergunta: 'Uma agência de São Paulo entende a imprensa carioca?',
    resposta:
      'O que decide uma pauta é o interesse do leitor, e isso a equipe estuda veículo por veículo. A Mclair lê os jornais do Rio, acompanha quem cobre cada assunto e já teve pautas de clientes publicadas no Extra, com imóveis do estado no centro da matéria.',
  },
  {
    pergunta: 'A Mclair tem escritório no Rio?',
    resposta:
      'Não. A agência fica em São Paulo. Clientes do Rio são atendidos a distância, com reunião por vídeo e contato diário por WhatsApp e telefone.',
  },
  {
    pergunta: 'Vocês colocam minha empresa no O Globo, no Extra ou na TV Globo?',
    resposta:
      'Ninguém honesto promete isso. A Mclair não tem parceria com esses veículos e não compra espaço editorial. O trabalho é levar ao repórter certo um fato novo, com número conferido e porta-voz pronto para falar. Se a redação achar que interessa ao leitor, publica.',
  },
  {
    pergunta: 'Já existe case de cliente com sede no Rio?',
    resposta:
      'Ainda não há um publicado no site. O que existe são matérias no Extra para duas leiloeiras atendidas pela Mclair. Numa delas, da Globo Leilões, 2.468 dos 2.693 imóveis anunciados ficavam no estado do Rio.',
  },
  {
    pergunta: 'Vocês atendem empresas de óleo e gás, cultura, turismo ou saúde?',
    resposta:
      'Atendemos, mas sem case publicado nesses setores. Na primeira conversa a Mclair diz com franqueza se a história da empresa tem chance na imprensa e em quais editorias.',
  },
  {
    pergunta: 'Como a equipe acompanha a cobertura sem estar no Rio?',
    resposta:
      'Cada matéria publicada entra no clipping com veículo, data e link. O relatório traz também as pautas em negociação e os pedidos de entrevista que chegaram. O contato com os repórteres corre por e-mail, mensagem e telefone, o que funciona de qualquer cidade.',
  },
  {
    pergunta: 'Em quanto tempo sai a primeira matéria?',
    resposta:
      'Não há prazo garantido, porque a decisão é da redação. Uma pauta com dado novo e fonte disponível costuma andar mais rápido. Os primeiros dias servem para achar essa pauta dentro da empresa.',
  },
];
