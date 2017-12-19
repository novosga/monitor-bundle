/**
 * Novo SGA - Monitor
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'
    
    var app = new Vue({
        el: '#monitor',
        data: {
            search: '',
            searchResult: [],
            servicos: [],
            atendimento: null,
            novoServico: '',
            novaPrioridade: ''
        },
        methods: {
            init: function () {
                var self = this;
                
                App.Websocket.connect();

                App.Websocket.on('connect', function () {
                    console.log('connected!');
                    App.Websocket.emit('register user', {
                        secret: wsSecret,
                        user: usuario.id,
                        unity: unidade.id
                    });
                });

                // ajax polling fallback
                App.Websocket.on('reconnect_failed', function () {
                    App.Websocket.connect();
                    console.log('ws timeout, ajax polling fallback');
                    self.update();
                });

                App.Websocket.on('register ok', function () {
                    console.log('registered!');
                });

                App.Websocket.on('update queue', function () {
                    console.log('do update!');
                    self.update();
                });
                
                self.update();
            },
            
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
                            App.Websocket.emit('change ticket', {
                                unity: unidade.id
                            });
                            $('.modal').modal('hide');
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
                            App.Websocket.emit('change ticket', {
                                unity: unidade.id
                            });
                            $('.modal').modal('hide');
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
                            App.Websocket.emit('change ticket', {
                                unity: unidade.id
                            });
                            $('.modal').modal('hide');
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
            }
        }
    });
    
    app.init();
})();