/* ------------------------------------------------------------------------
 * Página /servicos/assessoria-de-imprensa/belo-horizonte/. Fica num arquivo
 * próprio para não disputar assessoria-imprensa.ts com as outras cidades.
 * As perguntas abaixo são as mesmas no HTML e no FAQPage. A Mclair não tem
 * escritório em Minas nem case mineiro: as respostas dizem isso com todas
 * as letras e não citam encontro presencial.
 * --------------------------------------------------------------------- */
import type { Pergunta } from './assessoria-imprensa';

export const URL_ASSESSORIA_BH = '/servicos/assessoria-de-imprensa/belo-horizonte/';

export const SEO_ASSESSORIA_BH = {
  title: 'Assessoria de Imprensa em Belo Horizonte e MG | Mclair',
  h1: 'Assessoria de imprensa em Belo Horizonte',
  description:
    'Assessoria de imprensa em Belo Horizonte e Minas Gerais para mineração, café, startups, saúde e varejo, com pauta para a imprensa mineira e a nacional. Fale com a Mclair.',
  keywords:
    'assessoria de imprensa bh, assessoria de imprensa belo horizonte, assessoria de imprensa em belo horizonte, assessoria de imprensa mg, assessoria de imprensa minas gerais, mclair',
};

export const WA_ASSESSORIA_BH =
  'https://wa.me/5511988600372?text=' +
  encodeURIComponent('Olá Mclair! Vim pela página de Assessoria de Imprensa em Belo Horizonte e quero conversar sobre a minha empresa em Minas.');

export const FAQ_ASSESSORIA_BH: Pergunta[] = [
  {
    pergunta: 'Quanto custa contratar assessoria de imprensa em BH?',
    resposta:
      'Não existe tabela fechada. O valor sai do volume de pautas, do número de porta-vozes e de onde a empresa quer ser lida: só em Minas, no país ou nos dois. Antes de qualquer número, a Mclair faz um diagnóstico do que a sua empresa tem para contar. A página quanto custa assessoria de imprensa explica o que entra na conta.',
  },
  {
    pergunta: 'Uma agência de São Paulo consegue atender uma empresa mineira?',
    resposta:
      'Consegue, e a Mclair não esconde que está em São Paulo. Não há escritório em Minas. O atendimento é por videochamada, WhatsApp e telefone, com a mesma equipe todo mês. O que decide se a pauta sai é o fato e o dado, e isso se apura bem a distância.',
  },
  {
    pergunta: 'Vocês prometem matéria no Estado de Minas, em O Tempo ou na Itatiaia?',
    resposta:
      'Não. A Mclair não tem parceria com esses veículos e nenhum deles vende espaço na reportagem. O trabalho é chegar ao repórter certo com uma notícia de Minas apurada, número conferido e porta-voz que atende no mesmo dia. A decisão de publicar é da redação.',
  },
  {
    pergunta: 'Mineradora ou siderúrgica precisa de cuidado especial com a imprensa?',
    resposta:
      'Precisa. O setor é acompanhado de perto por repórteres de economia e de meio ambiente, e qualquer anúncio vem com perguntas sobre barragem, licença e comunidade vizinha. Antes de divulgar, o porta-voz treina essas perguntas e a empresa organiza os números que vai mostrar.',
  },
  {
    pergunta: 'Produtor de café do Sul de Minas ou do Cerrado tem espaço na mídia?',
    resposta:
      'Tem, quando traz algo que a redação não sabe: preço pago ao produtor, qualidade da safra, exportação, certificação. Minas é o maior estado produtor de café do Brasil, então a imprensa de agro acompanha o assunto o ano inteiro.',
  },
  {
    pergunta: 'Startup de BH deve buscar a imprensa mineira ou a de tecnologia?',
    resposta:
      'Em geral, as duas, em momentos diferentes. Rodada de investimento e número de clientes interessam aos portais de tecnologia e de negócios do país. Contratação, sede nova e história do fundador costumam render mais nos jornais de Belo Horizonte.',
  },
  {
    pergunta: 'Vocês atendem empresas do interior de Minas?',
    resposta:
      'Sim. Uberlândia, Juiz de Fora, Contagem e outras cidades entram no mesmo modelo de atendimento a distância. A lista de jornalistas muda conforme a região, com o jornal da cidade, a imprensa da capital e os veículos do setor.',
  },
  {
    pergunta: 'A Mclair já teve cliente em Minas Gerais?',
    resposta:
      'Os cases publicados no site não são de empresas mineiras. Eles mostram o método com tecnologia, leilões de imóveis e comunicação delicada numa aquisição. Na primeira conversa, a Kelly Pinheiro explica como esse método se aplica ao seu setor e à imprensa de Minas.',
  },
];
