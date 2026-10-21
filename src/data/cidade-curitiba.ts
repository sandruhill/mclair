/**
 * Dados da página /servicos/assessoria-de-imprensa/curitiba/. Ficam num
 * arquivo próprio para não disputar edição com assessoria-imprensa.ts. As
 * perguntas abaixo saem iguais no HTML e no FAQPage. Nada aqui foi
 * inventado: a Mclair não tem escritório no Paraná, não há case paranaense
 * publicado e os veículos citados entram só como contexto.
 */

import type { Pergunta } from './assessoria-imprensa';

export const URL_ASSESSORIA_CWB = '/servicos/assessoria-de-imprensa/curitiba/';

export const SEO_ASSESSORIA_CWB = {
  title: 'Assessoria de Imprensa em Curitiba e no Paraná | Mclair',
  h1: 'Assessoria de imprensa em Curitiba',
  description:
    'Assessoria de imprensa e de comunicação em Curitiba e no Paraná para indústria, tecnologia e saúde, com pauta para a imprensa paranaense e a nacional. Fale com a Mclair.',
  keywords:
    'assessoria de imprensa curitiba, assessoria de imprensa em curitiba, assessoria de comunicação curitiba, assessoria de imprensa paraná, assessoria de imprensa pr, agência de assessoria de imprensa curitiba, mclair',
};

export const WA_ASSESSORIA_CWB =
  'https://wa.me/5511988600372?text=' +
  encodeURIComponent('Olá Mclair! Vim pela página de Assessoria de Imprensa em Curitiba e quero conversar sobre a minha empresa no Paraná.');

export const FAQ_ASSESSORIA_CWB: Pergunta[] = [
  {
    pergunta: 'Quanto custa assessoria de imprensa em Curitiba?',
    resposta:
      'Não existe preço de vitrine que sirva para uma metalúrgica da CIC e para uma clínica do Batel ao mesmo tempo. O valor sai do escopo: quantos porta-vozes, quantas pautas por mês, se o foco é a imprensa do Paraná, a nacional ou a setorial, e se entra media training. A Mclair faz o diagnóstico na primeira conversa e só depois manda a proposta. A página sobre quanto custa assessoria de imprensa explica como o mercado cobra.',
  },
  {
    pergunta: 'A Mclair tem escritório em Curitiba?',
    resposta:
      'Não. A agência trabalha de São Paulo e atende clientes paranaenses a distância, com reunião por vídeo e contato diário por WhatsApp e telefone.',
  },
  {
    pergunta: 'Vocês conseguem matéria na Gazeta do Povo ou na RPC?',
    resposta:
      'Ninguém sério consegue prometer isso. A Mclair não tem parceria com a Gazeta do Povo, o Bem Paraná, a Tribuna ou a RPC, e nenhum deles vende espaço editorial. O trabalho é levar ao repórter certo um fato com dado do Paraná e um porta-voz que atende no mesmo dia. A decisão de publicar é dele.',
  },
  {
    pergunta: 'Assessoria de comunicação e assessoria de imprensa são a mesma coisa?',
    resposta:
      'Há diferença. Assessoria de imprensa trata da relação com jornalistas: pauta, release, entrevista e clipping. Assessoria de comunicação costuma incluir também redes sociais, marca e comunicação interna. A Mclair faz assessoria de imprensa e também tem projetos de marca e redes sociais, como o da Zbra.',
  },
  {
    pergunta: 'Vocês já atenderam alguma empresa do Paraná?',
    resposta:
      'Entre os cases publicados no site da Mclair, nenhum é paranaense. Os exemplos desta página vêm de outros estados e estão aqui para mostrar o método com indústria e tecnologia.',
  },
  {
    pergunta: 'Minha indústria vende para outras empresas. Imprensa adianta?',
    resposta:
      'Adianta, mas a imprensa certa muda. Quem compra máquina, peça ou serviço industrial lê revista setorial, portal de economia e a página de negócios do jornal do estado. Exportação nova ou contrato grande rendem mais do que a inauguração de um galpão.',
  },
  {
    pergunta: 'Clínica, hospital ou médico pode contratar assessoria de imprensa?',
    resposta:
      'Pode, e a pauta de saúde tem bastante espaço quando traz informação útil ao leitor. Só que médico segue as regras de publicidade do Conselho Federal de Medicina também quando dá entrevista. A pauta e a preparação do porta-voz são feitas dentro dessas regras.',
  },
  {
    pergunta: 'Startup de Curitiba deve mirar a imprensa local ou a de tecnologia?',
    resposta:
      'As duas, em momentos diferentes. Rodada de investimento e contratação costumam interessar ao jornal da cidade. Produto, dado de mercado e opinião de fundador sobre tecnologia encaixam melhor em portais especializados de alcance nacional.',
  },
  {
    pergunta: 'Vocês atendem Londrina, Maringá e o interior do Paraná?',
    resposta:
      'Sim, do mesmo jeito que Curitiba. Para uma empresa do interior, a lista de envio junta a imprensa da cidade dela, os veículos de Curitiba que cobrem o estado e a imprensa nacional do setor.',
  },
];
