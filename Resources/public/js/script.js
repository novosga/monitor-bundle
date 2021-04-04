/**
 * Novo SGA - Monitor
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'
    
    new Vue({
        el: '#monitor',
        data: {
            search: '',
            searchResult: [],
            servicos: [],
            atendimento: null,
            novoServico: '',
            novaPrioridade: '',
            unidade: (unidade || {}),
        },
        methods: {
            update: function () {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.monitor/ajax_update'),
                    data: {
                        ids: ids.join(',')
                    },
                    success: function (response) {
                        self.servicos = response.data;
                    }
                });
            },
            
            /**
             * Busca informacoes do atendimento pelo id.
             */
            view: function (atendimento) {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.monitor/info_senha/') + atendimento.id,
                    success: function (response) {
                        self.atendimento = response.data;
                        $('#dialog-view').modal('show');
                    }
                });
            },

            consulta: function () {
                $('#dialog-busca').modal('show');
                this.consultar();
            },

            consultar: function () {
                var self = this;
                
                App.ajax({
                    url: App.url('/novosga.monitor/buscar'),
                    data: {
                        numero: self.search
                    },
                    success: function (response) {
                        self.searchResult = response.data;
                    }
                });
            },

            transfere: function (atendimento) {
                this.atendimento = atendimento;
                $('#dialog-transfere').modal('show');
            },

            transferir: function (atendimento, novoServico, novaPrioridade) {
                var self = this;
                swal({
                    title: alertTitle,
                    text: alertTransferir,
                    type: "warning",
                    buttons: [
                        labelNao,
                        labelSim
                    ],
                    //dangerMode: true,
                })
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    
                    App.ajax({
                        url: App.url('/novosga.monitor/transferir/') + atendimento.id,
                        type: 'post',
                        data: {
                            servico: novoServico,
                            prioridade: novaPrioridade
                        },
                        success: function () {
                            $('.modal').modal('hide');
                            
                            if (!App.SSE.connected) {
                                self.update();
                            }
                        }
                    });
                });
            },

            reativar: function(atendimento) {
                var self = this;
                swal({
                    title: alertTitle,
                    text: alertReativar,
                    type: "warning",
                    buttons: [
                        labelNao,
                        labelSim
                    ],
                    //dangerMode: true,
                })
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    
                    App.ajax({
                        url: App.url('/novosga.monitor/reativar/') + atendimento.id,
                        type: 'post',
                        success: function () {
                            $('.modal').modal('hide');
                            
                            if (!App.SSE.connected) {
                                self.update();
                            }
                        }
                    });
                });
            },

            cancelar: function(atendimento) {
                var self = this;
                swal({
                    title: alertTitle,
                    text: alertCancelar,
                    type: "warning",
                    buttons: [
                        labelNao,
                        labelSim
                    ],
                    //dangerMode: true,
                })
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
            
                    App.ajax({
                        url: App.url('/novosga.monitor/cancelar/') + atendimento.id,
                        type: 'post',
                        success: function () {
                            $('.modal').modal('hide');
                            
                            if (!App.SSE.connected) {
                                self.update();
                            }
                        }
                    });
                });
            },
            
            totalPorSituacao: function (fila, prioridade) {
                var filtered = fila.filter(function (atendimento) {
                    if (prioridade) {
                        return atendimento.prioridade.peso > 0;
                    }
                    return atendimento.prioridade.peso === 0;
                });
                return filtered.length;
            },

            getItemFilaStyle(atendimento) {
                let styles = ['color: black']
                if (atendimento.prioridade.cor) {
                    styles.push(`color: ${atendimento.prioridade.cor}`)
                }
                return styles.join(';')
            }
        },
        mounted() {
            App.SSE.connect([
                `/unidades/${this.unidade.id}/fila`
            ]);

            App.SSE.onmessage = (e, data) => {
                this.update();
            };

            // ajax polling fallback
            App.SSE.ondisconnect = () => {
                this.update();
            };
            
            this.update();
        }
    });
})();